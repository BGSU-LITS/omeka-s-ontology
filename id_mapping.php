<?php
require __DIR__ . '/vendor/autoload.php';

use Commando\Command;

$command = new Command();
$command->setHelp(<<<EOT
Creates an SQL statement to map Omeka-S resource IDs to a given property.

Example usage:
php id_mapping.php resource vocabulary property > id_mapping.sql
EOT
);

$command->option()
    ->referToAs('resource')
    ->description('Resource template of items. Required.');

$command->option()
    ->referToAs('vocabulary')
    ->description('Vocabulary prefix of property. Required.');

$command->option()
    ->referToAs('property')
    ->description('Local name of property. Required.');

if (empty($command[0]) || empty($command[1]) || empty($command[2])) {
    $command->printHelp();
    exit;
}

echo 'SELECT resource.id, value.value FROM resource';
echo ' LEFT JOIN resource_template';
echo ' ON resource.resource_template_id = resource_template.id';
echo ' LEFT JOIN value ON resource.id = value.resource_id';
echo ' LEFT JOIN property ON value.property_id = property.id';
echo ' LEFT JOIN vocabulary ON property.vocabulary_id = vocabulary.id';
echo ' WHERE resource_template.label = "' . $command[0] . '"';
echo ' AND vocabulary.prefix = "' . $command[1] . '"';
echo ' AND property.local_name = "' . $command[2] . '"';
echo ';' . PHP_EOL;
