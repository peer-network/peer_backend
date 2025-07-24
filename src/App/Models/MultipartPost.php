<?php

namespace Fawaz\App\Models;

use Fawaz\App\ValidationException;
use Fawaz\Filter\PeerInputFilter;
use Fawaz\Services\JWTService;
use Fawaz\Utils\ResponseHelper;
use getID3;

class MultipartPost
{
    use ResponseHelper;
    protected string $eligibilityToken;
    protected array $media = [];
    protected JWTService $tokenService;

    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        if ($validate && !empty($data)) {
            $data = $this->validate($data, $elements);
        }

        $this->eligibilityToken = $data['eligibilityToken'] ?? '';
        $this->media = $data['media'] ?? [];
    }

    /**
     * Get Values of current state
     */
    public function getArrayCopy(): array
    {
        $att = [
            'eligibilityToken' => $this->eligibilityToken,
            'media' => $this->media,
        ];
        return $att;
    }


    /**
     * State Getter
     */
    public function getEligibilityToken(): string
    {
        return $this->eligibilityToken;
    }

    public function getMedia(): array
    {
        return $this->media;
    }


    /**
     * Apply Validation on provided request object
     */
    public function validate(array $data, array $elements = []): array
    {
        $inputFilter = $this->createInputFilter($elements);
        $inputFilter->setData($data);

        if ($inputFilter->isValid()) {
            return $inputFilter->getValues();
        }

        $validationErrors = $inputFilter->getMessages();

        foreach ($validationErrors as $field => $errors) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error;
            }
            $errorMessageString = implode("", $errorMessages);
            
            throw new ValidationException($errorMessageString);
        }
        return [];
    }

    
    
    /**
     * Apply Additional Validation on provided request object
     */
    public function applyAdditionalFilter(): void
    {

        if(empty($this->eligibilityToken)){
            throw new ValidationException("Token Should not be empty.", [30102]); // Token Should not be empty
        }

        if(empty($this->media) && !is_array($this->media)){
            throw new ValidationException("Media should not be empty", [30102]); // Media should not be empty
        }
    }

        
    /**
     * Apply Additional Validation on provided request object
     */
    public function validateEligibilityToken($tokenService): void
    {
        if(empty($this->eligibilityToken)){
            throw new ValidationException("Token Should not be empty.", [30102]); // Token Should not be empty
        }
        $isValidated = $tokenService->validateToken($this->eligibilityToken);
           
        if(empty($isValidated)){
            throw new ValidationException("Token Should be valid.", [40902]);
        }

    }
    

    /**
     * Ensure all media files in the array have the same content type.
     *
     * @throws ValidationException
     */
    public function validateMediaContentTypes(): void
    {
        if(empty($this->media) && !is_array($this->media)){
            throw new ValidationException("Media should not be empty", [30102]); // Media should not be empty
        }

        // Detect the first media type
        $detectedTypes = [];
        $maxFileSize = 1024 * 1024 * 500; // 500MB
        $currentMediaSize = 0;
        foreach ($this->media as $key => $media) {
            $currentMediaSize += $media->getSize();
            
            $type = $this->detectMediaType($media);
            if (!$type) {
                throw new ValidationException("Unknown media type detected at index $key.", [30259]); // Unknown media type detected at index
            }
            $detectedTypes[] = $type;
        }

        if ($currentMediaSize > $maxFileSize) {
            throw new ValidationException("Maximum file upload should be less than 500MB", [30261]); // Maximum file upload should be less than 500MB
        }

        // Check all media types are the same
        $firstType = $detectedTypes[0];
        foreach ($detectedTypes as $index => $type) {
            if ($type !== $firstType) {
                throw new ValidationException("Media content types mismatch: index $index is of type $type, expected $firstType.", [30266]); // Media content types mismatch
            }
        }
    }

    /**
     * Ensure all media files has 20 Photos, 2 Videos and 1 Audio.
     *
     * @throws ValidationException
     */
    public function validateMediaAllow(): void
    {
        if(empty($this->media) && !is_array($this->media)){
            throw new ValidationException("Media should not be empty", [30102]); // Media should not be empty
        }

        // Detect the first media type
        $imageCount = 0;
        $videoCount = 0;
        $audioCount = 0;
        $textCount = 0;
        foreach ($this->media as $key => $media) {
            $type = $this->detectMediaType($media);
            switch ($type) {
                case 'image':
                    $imageCount++;
                    break;
                case 'video':
                    $videoCount++;
                    break;
                case 'audio':
                    $audioCount++;
                    break;
                case 'text':
                    $textCount++;
                    break;
                default:
                    break;
            }
            if ($imageCount > 20) {
                throw new ValidationException("Image should not be more than 20", [30267]); // Image should not be more than 20
            }
            if ($videoCount > 2) {
                throw new ValidationException("Video should not be more than 2", [30267]); // Video should not be more than 2
            }
            if ($audioCount > 1) {
                throw new ValidationException("Audio should not be more than 1", [30267]); // Audio should not be more than 1
            }
            if ($textCount > 1) {
                throw new ValidationException("Text should not be more than 1", [30267]); // Text should not be more than 1
            }
        }     

    }


    /**
     * Ensure all media files in the array have the same content type.
     *
     * @throws ValidationException
     */
    public function validateSameContentTypes(): string|bool
    {
        if(empty($this->media) && !is_array($this->media)){
            return false;
        }

        // Detect the first media type
        $detectedTypes = [];
        foreach ($this->media as $key => $media) {
            $extension = pathinfo($media, PATHINFO_EXTENSION);
          
            $fileType = $this->getSubfolder(strtolower($extension));
            
            if (!$fileType) {
                return false;
            }
            $detectedTypes[] = $fileType;
        }

        // Check all media types are the same
        $firstType = $detectedTypes[0];
        foreach ($detectedTypes as $index => $type) {
            if ($type !== $firstType) {
                return false;
            }
        }
        return $firstType;
    }


    /**
     * Helper to determine media type based on file's mime type
     */
    private function detectMediaType(\Slim\Psr7\UploadedFile $media): ?string
    {
        $mimeType = $media->getClientMediaType();

        // Validate based on mime types
        if (in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/heic', 'image/heif', 'image/tiff'])) {
            return 'image';
        }

        if (in_array($mimeType, ['video/mp4', 'video/avi', 'video/mov', 'video/mkv', 'video/webm', 'video/quicktime', 'video/x-m4v', 'video/x-msvideo', 'video/3gpp', 'video/x-matroska'])) {
            return 'video';
        }

        if (in_array($mimeType, ['audio/mpeg', 'audio/wav', 'audio/webm'])) {
            return 'audio';
        }

        if (in_array($mimeType, ['text/plain', 'application/json', 'application/xml'])) {
            return 'text';
        }

        return null;
    }


    /**
     * Check if file exists
     */
    public function isFilesExists(){
        $isFileExists = true;

        foreach ($this->getMedia() as $key => $media) {

            $directoryPath = __DIR__ . "/../../../runtime-data/media/tmp";
            
            $filePath = "$directoryPath/$media";

            if(!file_exists($filePath)){
                $isFileExists = false;
                break;
            }

        }

        return $isFileExists;
    }

    /**
     * Move Uploaded File to Tmp Folder
     */
    public function moveFileToTmp(){
        $allMetadata = [];

        foreach ($this->getMedia() as $key => $media) {
            $originalType = $media->getClientMediaType();
            $fileName = $media->getClientFilename();
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            
            $tmpFilename = self::generateUUID(); 
            $directoryPath = __DIR__ . "/../../../runtime-data/media/tmp";
            
            if (!is_dir($directoryPath)) {
                try{
                    mkdir($directoryPath, 0777, true);
                }catch(\RuntimeException $e){
                    throw new \Exception("Directory does not exist: $directoryPath"); // Directory does not exist
                }
            }

            $filePath = "$directoryPath/$tmpFilename.$extension";

            try{
                $media->moveTo($filePath);
            }catch(\RuntimeException $e){
                throw new \Exception("Failed to move file: $directoryPath"); // Failed to move file
            }

            $metadata = [
                'fileName' => $tmpFilename.'.'.$extension,
                'mimeType' => $originalType,
                'mediaType' => $extension,
            ];

            $allMetadata[] = $tmpFilename.'.'.$extension;
        }

        return $allMetadata ;
    }

    
    /**
     * Move Uploaded File to Tmp Folder
     */
    public function moveFileTmpToMedia(): array {
        $allMetadata = [];

        $tmpFolder = __DIR__ . "/../../../runtime-data/media/tmp/";

        foreach ($this->getMedia() as $key => $media) {
             // Open the file stream
            $stream = new \Slim\Psr7\Stream(fopen($tmpFolder.$media, 'r'));

            // Create the UploadedFile object
            $uploadedFile = new \Slim\Psr7\UploadedFile(
                $stream, 
                null, 
                null
            );
            // Calculate Subfolder
            $extension = pathinfo($tmpFolder.$media, PATHINFO_EXTENSION);
            $subFolder = $this->getSubfolder(strtolower($extension));

            $directoryPath = __DIR__ . "/../../../runtime-data/media/".$subFolder;

            $filePath = "$directoryPath/$media";

            try {
                $uploadedFile->moveTo($filePath);
            } catch (\RuntimeException $e) {
                throw new \Exception("Failed to move file: $filePath");
            }

            $fileDetails = $this->getFileDetails($filePath);

            $getfileinfo = $this->getMediaDuration($filePath);

            $duration = $ratiofrm = $resolution = null;
            $size = $this->formatBytes($fileDetails['size']);

            if (in_array($subFolder, ['audio', 'video'])) {
                $duration = $getfileinfo['duration'] ?? null;
            }

            if ($subFolder === 'video') {
                $ratiofrm = $getfileinfo['ratiofrm'] ?? null;
            }

            if (in_array($subFolder, ['image', 'video'])) {
                $resolution = $getfileinfo['resolution'] ?? null;
            }

            $options = array_filter([
                'size' => $size,
                'duration' => $duration ? $this->formatDuration($duration) : null,
                'ratio' => $ratiofrm,
                'resolution' => $resolution
            ]);

            $allMetadata[] = [
                'path'    => "/$subFolder/$media",
                'options' => $options,
            ];
        }

        return isset($allMetadata) && is_array($allMetadata) ? $allMetadata : [];
    }


    private function formatDuration(float $durationInSeconds): string
	{
		$hours = (int) \floor($durationInSeconds / 3600);
		$minutes = (int) \floor(fmod($durationInSeconds, 3600) / 60);
		$seconds = (int) \floor(fmod($durationInSeconds, 60));
		$milliseconds = (int) \round(($durationInSeconds - \floor($durationInSeconds)) * 100);

		return \sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
	}

    
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return \sprintf('%.2f %s', $bytes, $units[$index]);
    }
    
    private function getMediaDuration(string $filePath): ?array
    {
        if (!\file_exists($filePath)) {
            return null;
        }

        try {
            $getID3 = new getID3();
            $fileInfo = $getID3->analyze($filePath);
            $information = [];

            if (!empty($fileInfo['video']['resolution_x']) && !empty($fileInfo['video']['resolution_y'])) {
              $width = $fileInfo['video']['resolution_x'];
              $height = $fileInfo['video']['resolution_y'];

              $gcd = gmp_intval(gmp_gcd($width, $height));
              $ratio = ($width / $gcd) . ':' . ($height / $gcd);
              $auflg = "{$width}x{$height}";
            }

            $information['duration'] = isset($fileInfo['playtime_seconds']) ? (float)$fileInfo['playtime_seconds'] : null;
            $information['ratiofrm'] = isset($ratio) ? $ratio : null;
            $information['resolution'] = isset($auflg) ? $auflg : null;

            return isset($information) ? (array)$information : null;
            
        } catch (\Exception $e) {
            \error_log("getID3 Error: " . $e->getMessage());
            return null;
        }
    }
        
    /**
     * Move Uploaded File to Tmp Folder
     */
    public function revertFileToTmp(): void 
    {
        $tmpFolder = __DIR__ . "/../../../runtime-data/media/tmp/";

        foreach ($this->getMedia() as $key => $media) {        

            // Calculate Subfolder
            $extension = pathinfo($media, PATHINFO_EXTENSION);
            $subFolder = $this->getSubfolder(strtolower($extension));

            var_dump($subFolder); exit;

            $directoryPath = __DIR__ . "/../../../runtime-data/media/".$subFolder;

            $filePath = "$directoryPath/$media";

            if(file_exists($filePath)){
               // Open the file stream
                $stream = new \Slim\Psr7\Stream(fopen($filePath, 'r'));

                // Create the UploadedFile object
                $uploadedFile = new \Slim\Psr7\UploadedFile(
                    $stream, 
                    null, 
                    null
                );

                try {
                    $uploadedFile->moveTo($tmpFolder.$media);
                } catch (\RuntimeException $e) {
                    throw new \Exception("Failed to move file: $filePath");
                } 
            }

        }
    }



    /**
     * generate File meta data
     */
    public function getFileDetails(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File does not exist: $filePath");
        }

        $details = [
            'basename'   => basename($filePath),
            'dirname'    => dirname($filePath),
            'extension'  => pathinfo($filePath, PATHINFO_EXTENSION),
            'size'       => filesize($filePath), // in Bytes
            'mime_type'  => mime_content_type($filePath),
        ];

        // Wenn es ein Bild ist
        $imageInfo = @getimagesize($filePath);
        if ($imageInfo !== false) {
            $details['width']  = $imageInfo[0];
            $details['height'] = $imageInfo[1];
            $details['ratio']  = round($imageInfo[0] / $imageInfo[1], 2);
        }

        return $details;
    }

    /**
     * Subfolder options
     */
    private function getSubfolder(string $fileType): string
    {
        if(in_array($fileType, ['webp', 'jpeg', 'jpg', 'png', 'gif', 'heic', 'heif', 'tiff'])){
            return 'image';
        }elseif(in_array($fileType, ['mp4', 'mov', 'avi', 'm4v', 'mkv', '3gp', 'webm', 'quicktime'])){
            return 'video';
        }elseif(in_array($fileType, ['mp3', 'wav'])){
            return 'audio';
        }elseif(in_array($fileType, ['txt'])){
            return 'text';
        }else{
            throw new \Exception("Cannot accept more file extension: $fileType"); // Cannot accept more file extension
        }

    }


    /**
     * Define validation
     */
    protected function createInputFilter(array $elements = []): PeerInputFilter
    {
        $specification = [
            'eligibilityToken' => [
                'required' => true,
                'validators' => [
                    ['name' => 'StringLength', 'options' => [
                        'min' => 30,
                        'max' => 1000,
                    ]],
                    ['name' => 'isString'],
                ],
            ],
            'media' => [
                'required' => true,
                'validators' => [
                    ['name' => 'IsArray'],
                ],
            ],
            
        ];

        if ($elements) {
            $specification = array_filter($specification, fn($key) => in_array($key, $elements, true), ARRAY_FILTER_USE_KEY);
        }

        return (new PeerInputFilter($specification));
    }

}
