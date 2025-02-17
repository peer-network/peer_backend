<?php

use PHPUnit\Framework\TestCase;
use GraphQL\Type\Schema;
use Fawaz\GraphQLSchemaBuilder;
use Tests\Helper\GraphQLSchemaBuilderHelper;

class GraphQLSchemaBuilderTest extends TestCase

{
    use GraphQLSchemaBuilderHelper;
    protected $schemaBuilder;
    protected $tokenDouble;
    protected $userMapperDouble;
    protected $fileReader;
    protected function setUp(): void
    {
        $dependicies = $this->createDependencies($this);
        $this->userMapperDouble = $dependicies['userMapper'];
        $this->tokenDouble = $dependicies['tokenService'];
        $this->fileReader = $dependicies['fileReader'];
        $this->schemaBuilder = new GraphQLSchemaBuilder(...$dependicies);
    }

    public function testBuildSchemaForGuestUser()
    {

        $expectedSchemaPath = dirname(__DIR__, levels: 2) . '/' . 'schemaguest.graphl';
        $this->fileReader->method('getFileContents')->with($expectedSchemaPath)->willReturn(file_get_contents($expectedSchemaPath));
        $schema = $this->schemaBuilder->build();

        $this->assertInstanceOf(Schema::class, $schema);
    }

    public function testBuildSchemaForRegularUser()
    {
        $this->sharedCorrectSchemaUsed(1, 0, 'schema.graphl');
    }

    public function testBuildSchemaForAdminUser()
    {
        $this->sharedCorrectSchemaUsed(1, 1, 'admin_schema.graphl');
    }

    private function sharedCorrectSchemaUsed(int $uid, int $role, string $schema )
    {
        $this->stubCurrentUserAndRole($uid, $role, $this->schemaBuilder, $this, $this->userMapperDouble, $this->tokenDouble);

        $expectedSchemaPath = dirname(__DIR__, levels: 2) . '/' . $schema;
        $this->fileReader->method('getFileContents')->with($expectedSchemaPath)->willReturn(file_get_contents($expectedSchemaPath));
        $schema = $this->schemaBuilder->build();
        $this->assertInstanceOf(Schema::class, $schema);
    }
}
