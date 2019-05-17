<?php

declare(strict_types=1);

namespace OpenAPIValidationTests\Schema\Keywords;

use OpenAPIValidation\Schema\Exception\ValidationKeywordFailed;
use OpenAPIValidation\Schema\Validator;
use OpenAPIValidationTests\Schema\SchemaValidatorTest;

final class ItemsTest extends SchemaValidatorTest
{
    public function testItValidatesItemsGreen() : void
    {
        $spec = <<<SPEC
schema:
  type: array
  items:
    type: string
SPEC;

        $schema = $this->loadRawSchema($spec);
        $data   = ['stringA', 'stringB'];

        (new Validator($schema, $data))->validate();
        $this->addToAssertionCount(1);
    }

    public function testItValidatesItemsNestedGreen() : void
    {
        $spec = <<<SPEC
schema:
  type: array
  items:
    type: array
    items:
      type: string
    minItems: 2
SPEC;

        $schema = $this->loadRawSchema($spec);
        $data   = [['stringA', 'stringB'], ['stringC', 'stringD', 'stringE']];

        (new Validator($schema, $data))->validate();
        $this->addToAssertionCount(1);
    }

    public function testItValidatesItemsRed() : void
    {
        $spec = <<<SPEC
schema:
  type: array
  items:
    type: string
SPEC;

        $schema = $this->loadRawSchema($spec);
        $data   = [1, 2];

        try {
            (new Validator($schema, $data))->validate();
        } catch (ValidationKeywordFailed $e) {
            $this->assertEquals('type', $e->keyword());
        }
    }

    public function testItValidatesItemsNestedRed() : void
    {
        $spec = <<<SPEC
schema:
  type: array
  items:
    type: array
    items:
      type: string
    minItems: 2
SPEC;

        $schema = $this->loadRawSchema($spec);
        $data   = [['stringA', 'stringB'], [12, 13]];

        try {
            (new Validator($schema, $data))->validate();
        } catch (ValidationKeywordFailed $e) {
            $this->assertEquals('type', $e->keyword());
        }
    }
}
