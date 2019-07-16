<?php

declare(strict_types=1);

namespace OpenAPIValidationTests\PSR7\Validators;

use GuzzleHttp\Psr7\ServerRequest;
use OpenAPIValidation\PSR7\Exception\Validation\InvalidBody;
use OpenAPIValidation\PSR7\ValidatorBuilder;
use OpenAPIValidation\Schema\Exception\FormatMismatch;
use PHPUnit\Framework\TestCase;
use function GuzzleHttp\Psr7\parse_request;

class BodyValidatorTest extends TestCase
{
    /**
     * @return array<array<string>> of arguments
     */
    public function dataProviderGreen() : array
    {
        return [
            // Normal multipart message
            [
                <<<HTTP
POST /multipart HTTP/1.1
Content-Length: 428
Content-Type: multipart/form-data; boundary=----WebKitFormBoundaryOmz20xyMCkE27rN7

------WebKitFormBoundaryOmz20xyMCkE27rN7
Content-Disposition: form-data; name="id"
Content-Type: text/plain

123e4567-e89b-12d3-a456-426655440000
------WebKitFormBoundaryOmz20xyMCkE27rN7
Content-Disposition: form-data; name="address"
Content-Type: application/json

{
  "street": "3, Garden St",
  "city": "Hillsbery, UT"
}
------WebKitFormBoundaryOmz20xyMCkE27rN7
Content-Disposition: form-data; name="profileImage "; filename="image1.png"
Content-Type: application/octet-steam

{...file content...}
------WebKitFormBoundaryOmz20xyMCkE27rN7--
HTTP
,
            ],
            // multiple files with the same part name (array of files)
            [
                <<<HTTP
POST /multipart/files HTTP/1.1
Content-Length: 2740
Content-Type: multipart/form-data; boundary=----WebKitFormBoundaryWfPNVh4wuWBlyEyQ

------WebKitFormBoundaryWfPNVh4wuWBlyEyQ
Content-Disposition: form-data; name="fileName"; filename="file1.txt"
Content-Type: text/plain

[file content goes there]
------WebKitFormBoundaryWfPNVh4wuWBlyEyQ
Content-Disposition: form-data; name="fileName"; filename="file2.png"
Content-Type: image/png

[file content goes there]
------WebKitFormBoundaryWfPNVh4wuWBlyEyQ
Content-Disposition: form-data; name="fileName"; filename="file3.jpg"
Content-Type: image/jpeg

[file content goes there]
------WebKitFormBoundaryWfPNVh4wuWBlyEyQ--
HTTP
,
            ],
            // specified encoding for one part
            [
                <<<HTTP
POST /multipart/encoding HTTP/1.1
Content-Length: 2740
Content-Type: multipart/form-data; boundary=----WebKitFormBoundaryWfPNVh4wuWBlyEyQ

------WebKitFormBoundaryWfPNVh4wuWBlyEyQ
Content-Disposition: form-data; name="image"; filename="file1.txt"
Content-Type: specific/type

[file content goes there]
------WebKitFormBoundaryWfPNVh4wuWBlyEyQ--
HTTP
,
            ],
            // specified headers for one part
            [
                <<<HTTP
POST /multipart/encoding HTTP/1.1
Content-Length: 2740
Content-Type: multipart/form-data; boundary=----WebKitFormBoundaryWfPNVh4wuWBlyEyQ

------WebKitFormBoundaryWfPNVh4wuWBlyEyQ
Content-Disposition: form-data; name="image"; filename="file1.txt"
Content-Type: specific/type
X-Custom-Header: string value goes here

[file content goes there]
------WebKitFormBoundaryWfPNVh4wuWBlyEyQ--
HTTP
,
            ],
        ];
    }

    /**
     * @return array<array<string,string>> of arguments
     */
    public function dataProviderRed() : array
    {
        return [
            // wrong data in one of the parts
            [
                <<<HTTP
POST /multipart HTTP/1.1
Content-Length: 428
Content-Type: multipart/form-data; boundary=----WebKitFormBoundaryOmz20xyMCkE27rN7

------WebKitFormBoundaryOmz20xyMCkE27rN7
Content-Disposition: form-data; name="id"
Content-Type: text/plain

wrong uuid
------WebKitFormBoundaryOmz20xyMCkE27rN7
Content-Disposition: form-data; name="address"
Content-Type: application/json

{
  "street": "3, Garden St",
  "city": "Hillsbery, UT"
}
------WebKitFormBoundaryOmz20xyMCkE27rN7
Content-Disposition: form-data; name="profileImage "; filename="image1.png"
Content-Type: application/octet-steam

{...file content...}
------WebKitFormBoundaryOmz20xyMCkE27rN7--
HTTP
,
                InvalidBody::class,
            ],
            // wrong encoding for one of the part
            [
                <<<HTTP
POST /multipart/encoding HTTP/1.1
Content-Length: 2740
Content-Type: multipart/form-data; boundary=----WebKitFormBoundaryWfPNVh4wuWBlyEyQ

------WebKitFormBoundaryWfPNVh4wuWBlyEyQ
Content-Disposition: form-data; name="image"; filename="file1.txt"
Content-Type: invalid/type

[file content goes there]
------WebKitFormBoundaryWfPNVh4wuWBlyEyQ--
HTTP
,
                InvalidBody::class,
            ],
            // wrong header for one part
            [
                <<<HTTP
POST /multipart/encoding HTTP/1.1
Content-Length: 2740
Content-Type: multipart/form-data; boundary=----WebKitFormBoundaryWfPNVh4wuWBlyEyQ

------WebKitFormBoundaryWfPNVh4wuWBlyEyQ
Content-Disposition: form-data; name="image"; filename="file1.txt"
Content-Type: specific/type
X-Custom-Header-WRONG: string value goes here

[file content goes there]
------WebKitFormBoundaryWfPNVh4wuWBlyEyQ--
HTTP
,
                FormatMismatch::class,
            ],
        ];
    }

    /**
     * @dataProvider dataProviderGreen
     */
    public function testValidateMultipartGreen(string $message) : void
    {
        $specFile = __DIR__ . '/../../stubs/multipart.yaml';

        $request       = parse_request($message); // convert a text HTTP message to a PSR7 message
        $serverRequest = new ServerRequest(
            $request->getMethod(),
            $request->getUri(),
            $request->getHeaders(),
            $request->getBody()
        );

        $validator = (new ValidatorBuilder())->fromYamlFile($specFile)->getServerRequestValidator();
        $validator->validate($serverRequest);
        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider dataProviderRed
     */
    public function testValidateMultipartRed(string $message, string $expectedExceptionClass) : void
    {
        $this->expectException($expectedExceptionClass);

        $specFile = __DIR__ . '/../../stubs/multipart.yaml';

        $request       = parse_request($message); // convert a text HTTP message to a PSR7 message
        $serverRequest = new ServerRequest(
            $request->getMethod(),
            $request->getUri(),
            $request->getHeaders(),
            $request->getBody()
        );

        $validator = (new ValidatorBuilder())->fromYamlFile($specFile)->getServerRequestValidator();
        $validator->validate($serverRequest);
    }
}
