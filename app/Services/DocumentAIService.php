<?php

namespace App\Services;

use Google\Cloud\DocumentAi\V1\DocumentProcessorServiceClient;
use Google\Cloud\DocumentAi\V1\RawDocument;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;


class DocumentAIService
{
    protected DocumentProcessorServiceClient $client;
    protected string $projectId;
    protected string $location;

    public function __construct()
    {
        // Initialize the Document AI client using credentials configured for the environment.
        $this->client = new DocumentProcessorServiceClient();
        $this->projectId = env('GOOGLE_PROJECT_ID');
        $this->location = env('GOOGLE_DOCAI_LOCATION');
    }
    
    /**
     * Processes a document using the specified processor.
     *
     * @param string $processorId The ID of the Document AI processor to use.
     * @param string $fileContent The raw content of the file to process.
     * @param string $mimeType The MIME type of the file.
     * @return \Google\Cloud\DocumentAi\V1\ProcessResponse
     */

    protected function processDocument(string $processorId, string $fileContent, string $mimeType)
    {
        $processorName = $this->client->processorName($this->projectId, $this->location, $processorId);
        $rawDocument = new RawDocument(['content' => $fileContent, 'mime_type' => $mimeType]);

        return $this->client->processDocument(['name' => $processorName, 'rawDocument' => $rawDocument]);
    }
    
    /**
     * Validates a Certificate document for type and extracts critical metadata.
     *
     * @param UploadedFile $file The uploaded file object.
     * @param string $uploaderFullName The expected full name of the user.
     * @return array Returns ['is_valid' => bool, 'reason' => string, 'extracted_data' => array]
     */

 public function validateResearchDocument(UploadedFile $file, string $uploaderFullName): array
    {
        $fileContent = file_get_contents($file->getRealPath());
        $mimeType = $file->getClientMimeType();
        $extractedData = [];
        $extractedAuthors = [];

        try {
//premade for classifier
            // $classifierId = env('DOCAI_RESEARCH_CLASSIFIER_ID');
            // $classifiedType = 'UNKNOWN'; 

            // $classificationResponse = $this->processDocument($classifierId, $fileContent, $mimeType);
            
            
            // foreach ($classificationResponse->getDocument()->getEntities() as $entity) {
            //     if ($entity->getType() === 'THESIS' || $entity->getType() === 'JOURNAL_ARTICLE') {
            //         $classifiedType = $entity->getType();
            //         break;
            //     }
            // }

            // if ($classifiedType === 'UNKNOWN' || $classifiedType === 'OTHER_DOCUMENT') {
            //      return ['is_valid' => false, 'reason' => 'Document rejected: Classified as unaccepted type.', 'extracted_data' => $extractedData];
            // }

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
                    // Get other metadata (Title, Institution, Date)
                    $extractedData[$type] = $value;
                    if (array_key_exists($type, $requiredFields)) {
                        $requiredFields[$type] = true;
                    }
                }
            }

            // --- 3. VALIDATION ENFORCEMENT ---

            // Check for missing required fields
            if (in_array(false, $requiredFields)) {
                $missing = array_keys(array_filter($requiredFields, fn($v) => !$v));
                return ['is_valid' => false, 'reason' => 'Missing required fields from document: ' . implode(', ', $missing), 'extracted_data' => $extractedData];
            }

            // Co-Author Check (Uploader must be in the Author_List)
            $normalizedUploaderName = strtolower(trim($uploaderFullName));
            $uploaderIsAuthor = false;

            foreach ($extractedAuthors as $authorName) {
                if (strtolower(trim($authorName)) === $normalizedUploaderName) {
                    $uploaderIsAuthor = true;
                    break;
                }
            }

            if (!$uploaderIsAuthor) {
                return ['is_valid' => false, 'reason' => "Uploader ('{$uploaderFullName}') is not listed in the extracted Author List.", 'extracted_data' => $extractedData];
            }

            // if passed
            $extractedData['Author_List'] = $extractedAuthors; // Add the full list back
            return ['is_valid' => true, 'reason' => 'Research document validated successfully.', 'extracted_data' => $extractedData];

        } catch (\Exception $e) {
            Log::error('Research DocAI Processing Failed: ' . $e->getMessage());
            return ['is_valid' => false, 'reason' => 'An API error occurred during processing.', 'extracted_data' => $extractedData];
        }
    }

    
    public function validateCertificate(UploadedFile $file, string $uploaderFullName): array
    {
        $fileContent = file_get_contents($file->getRealPath());
        $mimeType = $file->getClientMimeType();
        $extractedData = [];

        try {
            // --- CLASSIFICATION CHECK ---
            $classifierId = env('GOOGLE_DOCAI_CLASSIFIER_ID');
            $classificationResponse = $this->processDocument($classifierId, $fileContent, $mimeType);
            
            $classifiedType = 'UNKNOWN';
            
            foreach ($classificationResponse->getDocument()->getEntities() as $entity) {
                if ($entity->getType() === 'CERTIFICATE' || $entity->getType() === 'DIPLOMA') {
                    $classifiedType = $entity->getType();
                    break;
                }
            }

            if ($classifiedType === 'UNKNOWN' || $classifiedType === 'OTHER_DOCUMENT') {
                 return ['is_valid' => false, 'reason' => 'Document rejected: Classified as an unaccepted type.', 'extracted_data' => $extractedData];
            }


            // --- 2. EXTRACTION CHECK ---
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

            // --- 3. VALIDITY ENFORCEMENT ---

            // Check for missing required fields
            if (in_array(false, $requiredFields)) {
                $missing = array_keys(array_filter($requiredFields, fn($v) => !$v));
                return ['is_valid' => false, 'reason' => 'Missing required fields: ' . implode(', ', $missing), 'extracted_data' => $extractedData];
            }

            // Check User Name Match
            $expectedName = strtolower(trim($uploaderFullName));
            $extractedName = strtolower(trim($extractedData['User_Full_Name'] ?? ''));

            if ($extractedName !== $expectedName) {
                return ['is_valid' => false, 'reason' => "Extracted name ('{$extractedName}') does not match expected user name.", 'extracted_data' => $extractedData];
            }
            // reserve for date validity

            // Check Date Validity 
            // $completionDate = strtotime($extractedData['Date_Completed']);
            // if ($completionDate > time()) {
            //     return ['is_valid' => false, 'reason' => 'Completion date is in the future.', 'extracted_data' => $extractedData];
            // }

            return ['is_valid' => true, 'reason' => 'Certificate validated successfully.', 'extracted_data' => $extractedData];

        } catch (\Exception $e) {
            Log::error('Document AI Processing Failed: ' . $e->getMessage(), ['processor_id' => $classifierId ?? $extractorId]);
            return ['is_valid' => false, 'reason' => 'An API error occurred during processing.', 'extracted_data' => $extractedData];
        }
    }
}