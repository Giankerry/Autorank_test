<?php

namespace App\Services;

use Google\Cloud\DocumentAI\V1\Client\DocumentProcessorServiceClient;
use Google\Cloud\DocumentAI\V1\RawDocument;
use Google\Cloud\DocumentAI\V1\ProcessRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class DocumentAIService
{
    protected DocumentProcessorServiceClient $client;
    protected string $projectId;
    protected string $location;

    public function __construct()
    {
        $keyFilePath = env('GOOGLE_CLOUD_KEY_FILE', storage_path('app/google/autorank-473117-4e4340e8ab52.json'));

        if (!file_exists($keyFilePath)) {
            throw new \Exception("Google Cloud key file not found at path: {$keyFilePath}");
        }

        $this->projectId = env('GOOGLE_PROJECT_ID', '');
        $this->location = env('GOOGLE_DOCAI_LOCATION', 'us');

        // 👇 Determine proper API endpoint
        $apiEndpoint = $this->location . '-documentai.googleapis.com';

        // ✅ Initialize Document AI client with proper endpoint + timeout
        $this->client = new DocumentProcessorServiceClient([
            'credentials' => $keyFilePath,
            'apiEndpoint' => $apiEndpoint,
            'transportConfig' => [
                'rest' => [
                    'restOptions' => [
                        'timeout' => 120,          // 2 minutes
                        'connect_timeout' => 30,   // 30 seconds
                    ],
                ],
            ],
        ]);
    }

    /**
     * Process a document using a specific processor.
     */
    protected function processDocument(string $processorId, string $fileContent, string $mimeType)
    {
        if (empty($processorId)) {
            throw new \InvalidArgumentException('Processor ID cannot be empty.');
        }

        $processorName = $this->client->processorName($this->projectId, $this->location, $processorId);
        $rawDocument = new RawDocument(['content' => $fileContent, 'mime_type' => $mimeType]);

        $processRequest = new ProcessRequest([
            'name' => $processorName,
            'raw_document' => $rawDocument
        ]);

        return $this->client->processDocument($processRequest);
    }

    /**
     * Validate Research Document.
     */
    public function validateResearchDocument(UploadedFile $file, string $uploaderFullName): array
    {
        $fileContent = file_get_contents($file->getRealPath());
        $mimeType = $file->getClientMimeType();
        $extractedData = [];
        $extractedAuthors = [];

        try {
            $extractorId = env('GOOGLE_DOCAI_RESEARCH_EXTRACTOR_ID');
            $extractionResponse = $this->processDocument($extractorId, $fileContent, $mimeType);

            $requiredFields = ['Author_List' => false, 'Document_Title' => false];

            foreach ($extractionResponse->getDocument()->getEntities() as $entity) {
                $type = $entity->getType();
                $value = $entity->getMentionText();

                if ($type === 'Author_List') {
                    $extractedAuthors[] = $value;
                    $requiredFields['Author_List'] = true;
                } else {
                    $extractedData[$type] = $value;
                    if (array_key_exists($type, $requiredFields)) {
                        $requiredFields[$type] = true;
                    }
                }
            }

            if (in_array(false, $requiredFields)) {
                $missing = array_keys(array_filter($requiredFields, fn($v) => !$v));
                return [
                    'is_valid' => false,
                    'reason' => 'Missing required fields: ' . implode(', ', $missing),
                    'extracted_data' => $extractedData
                ];
            }

            // ✅ More flexible name matching (checks surname order-insensitive)
            $normalize = fn($name) => preg_replace('/[^a-z\s]/', '', strtolower($name));
            $normalizedUploader = $normalize($uploaderFullName);

            $uploaderWords = array_filter(explode(' ', $normalizedUploader));

            $uploaderIsAuthor = false;
            foreach ($extractedAuthors as $authorName) {
                $normalizedAuthor = $normalize($authorName);
                $authorWords = array_filter(explode(' ', $normalizedAuthor));

                $matchCount = count(array_intersect($uploaderWords, $authorWords));
                $similarity = $matchCount / max(count($uploaderWords), 1);

                if ($similarity >= 0.7) {
                    $uploaderIsAuthor = true;
                    break;
                }
            }

            if (!$uploaderIsAuthor) {
                return [
                    'is_valid' => false,
                    'reason' => "Uploader ('{$uploaderFullName}') not found in Author List.",
                    'extracted_data' => $extractedData
                ];
            }

            $extractedData['Author_List'] = $extractedAuthors;
            return [
                'is_valid' => true,
                'reason' => 'Research document validated successfully.',
                'extracted_data' => $extractedData
            ];
        } catch (\Exception $e) {
            Log::error('Research DocAI Processing Failed: ' . $e->getMessage());
            return [
                'is_valid' => false,
                'reason' => 'An API error occurred during processing.',
                'extracted_data' => $extractedData
            ];
        }
    }

    /**
     * Validate Certificate Document.
     */
    public function validateCertificate(UploadedFile $file, string $uploaderFullName): array
    {
        $fileContent = file_get_contents($file->getRealPath());
        $mimeType = $file->getClientMimeType();
        $extractedData = [];

        try {
            $classifierId = env('GOOGLE_DOCAI_CLASSIFIER_ID');
            $classificationResponse = $this->processDocument($classifierId, $fileContent, $mimeType);

            $classifiedType = 'UNKNOWN';
            foreach ($classificationResponse->getDocument()->getEntities() as $entity) {
                if (in_array($entity->getType(), ['CERTIFICATE', 'DIPLOMA'])) {
                    $classifiedType = $entity->getType();
                    break;
                }
            }

            if ($classifiedType === 'UNKNOWN') {
                return [
                    'is_valid' => false,
                    'reason' => 'Document rejected: Classified as an unaccepted type.',
                    'extracted_data' => $extractedData
                ];
            }

            $extractorId = env('GOOGLE_DOCAI_EXTRACTOR_ID');
            $extractionResponse = $this->processDocument($extractorId, $fileContent, $mimeType);

            $requiredFields = [
                'User_Full_Name' => false,
                'Issuing_Organization' => false,
                'Date_Completed' => false
            ];

            foreach ($extractionResponse->getDocument()->getEntities() as $entity) {
                $type = $entity->getType();
                $value = $entity->getMentionText();

                if (array_key_exists($type, $requiredFields)) {
                    $extractedData[$type] = $value;
                    $requiredFields[$type] = true;
                }
            }

            if (in_array(false, $requiredFields)) {
                $missing = array_keys(array_filter($requiredFields, fn($v) => !$v));
                return [
                    'is_valid' => false,
                    'reason' => 'Missing required fields: ' . implode(', ', $missing),
                    'extracted_data' => $extractedData
                ];
            }

            // ✅ Smarter name comparison
            $normalize = fn($name) => preg_replace('/[^a-z\s]/', '', strtolower($name));
            $expected = $normalize($uploaderFullName);
            $extracted = $normalize($extractedData['User_Full_Name'] ?? '');

            $expectedWords = array_filter(explode(' ', $expected));
            $extractedWords = array_filter(explode(' ', $extracted));

            $matchCount = count(array_intersect($expectedWords, $extractedWords));
            $similarity = $matchCount / max(count($expectedWords), 1);

            if ($similarity < 0.7) {
                return [
                    'is_valid' => false,
                    'reason' => "Extracted name ('{$extractedData['User_Full_Name']}') does not match uploader name.",
                    'extracted_data' => $extractedData
                ];
            }

            return [
                'is_valid' => true,
                'reason' => 'Certificate validated successfully.',
                'extracted_data' => $extractedData
            ];
        } catch (\Exception $e) {
            Log::error('Document AI Processing Failed: ' . $e->getMessage(), [
                'processor_id' => $classifierId ?? $extractorId ?? 'unknown'
            ]);

            return [
                'is_valid' => false,
                'reason' => 'An API error occurred during processing.',
                'extracted_data' => $extractedData
            ];
        }
    }
}
