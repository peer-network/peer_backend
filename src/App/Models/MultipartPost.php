<?php

namespace Fawaz\App\Models;

use Fawaz\App\ValidationException;
use Fawaz\Filter\PeerInputFilter;
use Fawaz\Services\JWTService;

class MultipartPost
{
    protected string $postId;
    protected string $eligibilityToken;
    protected array $media = [];
    protected JWTService $tokenService;

    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        if ($validate && !empty($data)) {
            $data = $this->validate($data, $elements);
        }

        $this->postId = $data['postId'] ?? '';
        $this->eligibilityToken = $data['eligibilityToken'] ?? '';
        $this->media = $data['media'] ?? [];
    }

    /**
     * Get Values of current state
     */
    public function getArrayCopy(): array
    {
        $att = [
            'postId' => $this->postId,
            'eligibilityToken' => $this->eligibilityToken,
            'media' => $this->media,
        ];
        return $att;
    }


    /**
     * State Getter
     */
    public function getPostId(): string
    {
        return $this->postId;
    }

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
        if(empty($this->postId)){
            throw new ValidationException("postId should not be empty.", [0000]); // postId should not be empty
        }

        if(empty($this->eligibilityToken)){
            throw new ValidationException("Token Should not be empty.", [0000]); // Token Should not be empty
        }

        if(empty($this->media) && !is_array($this->media)){
            throw new ValidationException("Media should not be empty", [0000]); // Media should not be empty
        }
    }

        
    /**
     * Apply Additional Validation on provided request object
     */
    public function validateEligibilityToken($tokenService): void
    {
        if(empty($this->eligibilityToken)){
            throw new ValidationException("Token Should not be empty.", [0000]); // Token Should not be empty
        }
        $isValidated = $tokenService->validateToken($this->eligibilityToken);
           
        if(empty($isValidated)){
            throw new ValidationException("Token Should be valid.", [0000]); // Token Should be valid
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
            throw new ValidationException("Media should not be empty", [0000]); // Media should not be empty
        }

        // Detect the first media type
        $detectedTypes = [];
        foreach ($this->media as $key => $media) {
            $type = $this->detectMediaType($media);
            if (!$type) {
                throw new ValidationException("Unknown media type detected at index $key.", [0000]); // Unknown media type detected at index
            }
            $detectedTypes[] = $type;
        }

        // Check all media types are the same
        $firstType = $detectedTypes[0];
        foreach ($detectedTypes as $index => $type) {
            if ($type !== $firstType) {
                throw new ValidationException("Media content types mismatch: index $index is of type $type, expected $firstType.", [0000]); // Media content types mismatch
            }
        }
    }

    /**
     * Helper to determine media type based on file's mime type
     */
    public function detectMediaType(\Slim\Psr7\UploadedFile $media): ?string
    {
        $mimeType = $media->getClientMediaType();

        // Validate based on mime types
        if (in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/heic', 'image/heif', 'image/tiff'])) {
            return 'image';
        }

        if (in_array($mimeType, ['video/mp4', 'video/avi', 'video/mov', 'video/mkv', 'video/webm'])) {
            return 'video';
        }

        if (in_array($mimeType, ['audio/mpeg', 'audio/wav', 'audio/aac', 'audio/ogg'])) {
            return 'audio';
        }

        if (in_array($mimeType, ['text/plain', 'application/json', 'application/xml'])) {
            return 'text';
        }

        return null;
    }



    /**
     * Define validation
     */
    protected function createInputFilter(array $elements = []): PeerInputFilter
    {
        $specification = [
            'postId' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
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
