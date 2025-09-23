<?php
use PHPUnit\Framework\TestCase;

class Base64FileHandlerTest extends TestCase
{
    public function testBasicEncoding()
    {
        $original = 'Hello, world!';
        $encoded = base64_encode($original);
        $this->assertEquals('SGVsbG8sIHdvcmxkIQ==', $encoded);
    }

    public function testBasicDecoding()
    {
        $encoded = 'SGVsbG8sIHdvcmxkIQ==';
        $decoded = base64_decode($encoded, true);
        $this->assertEquals('Hello, world!', $decoded);
    }


    public function testDecodeInvalidCharactersFails()
    {
        $invalid = '### not base64 @@@';
        $this->assertFalse(base64_decode($invalid, true));
    }

    public function testDecodeWithSpacesSucceeds()
    {
        $withSpaces = 'SG Vs bG8s I Hdv cmxk IQ =='; // Still "Hello, world!"
        $decoded = base64_decode($withSpaces, true);
        $this->assertEquals('Hello, world!', $decoded);
    }

    public function testDecodeWithIncorrectPaddingFails()
    {
        $badPadding = 'SGVsbG8sIHdvcmxkIQ='; // Incorrect padding (1 = instead of 2)
        $this->assertFalse(base64_decode($badPadding, true));
    }

    public function testRoundTripWithBinaryData()
    {
        $binary = random_bytes(64);
        $encoded = base64_encode($binary);
        $decoded = base64_decode($encoded, true);
        $this->assertEquals($binary, $decoded);
    }

    public function testDetectBase64StringPositive()
    {
        $base64 = 'U29tZSB2YWxpZCBiYXNlNjQ='; // "Some valid base64"
        $this->assertTrue($this->isProbablyBase64($base64));
    }

    public function testDetectBase64StringNegative()
    {
        $notBase64 = 'this_is_not@base64';
        $this->assertFalse($this->isProbablyBase64($notBase64));
    }

    public function testDecodeBase64PngHeader()
    {
        $base64 = 'iVBORw0KGgo='; // First bytes of PNG file
        $decoded = base64_decode($base64, true);
        $this->assertEquals("\x89PNG\r\n\x1A\n", $decoded);
    }

    public function testDecodeBase64JpegHeader()
    {
        $base64 = '/9j/'; // JPEG starts with 0xFFD8
        $decoded = base64_decode($base64, true);
        $this->assertStringStartsWith("\xFF\xD8", $decoded);
    }

    public function testDecodeBase64WithDataUri()
    {
        $dataUri = 'data:image/png;base64,iVBORw0KGgo=';
        $parts = explode(',', $dataUri);
        $this->assertCount(2, $parts);

        $decoded = base64_decode($parts[1], true);
        $this->assertEquals("\x89PNG\r\n\x1A\n", $decoded);
    }

    public function testBase64PaddingHandling()
    {
        $original = 'Pad!';
        $encoded = base64_encode($original); // Should end in '='
        $this->assertStringEndsWith('=', $encoded);
        $decoded = base64_decode($encoded, true);
        $this->assertEquals($original, $decoded);
    }

    public function testBase64RejectsEmoji()
    {
        $this->assertFalse(base64_decode('😂😂😂😂😂', true));
    }

    public function testDoubleEncodingShouldNotEqualOriginal()
    {
        $original = 'test';
        $doubleEncoded = base64_encode(base64_encode($original));
        $this->assertNotEquals($original, $doubleEncoded);
        $this->assertEquals($original, base64_decode(base64_decode($doubleEncoded, true), true));
    }

    public function testUtf8Characters()
    {
        $original = 'Привет 🌍';
        $encoded = base64_encode($original);
        $decoded = base64_decode($encoded, true);
        $this->assertEquals($original, $decoded);
    }

    public function testBinaryNoiseIsPreserved()
    {
        $noise = "\x00\xFF\xAB\xCD\xEF";
        $encoded = base64_encode($noise);
        $decoded = base64_decode($encoded, true);
        $this->assertEquals($noise, $decoded);
    }
    private function isProbablyBase64(string $input): bool
    {
        // Not empty, valid base64, matches character set, and divisible by 4
        return $input !== '' &&
               base64_decode($input, true) !== false &&
               preg_match('/^[A-Za-z0-9\/+=]+$/', $input) &&
               strlen($input) % 4 === 0;
    }
}
