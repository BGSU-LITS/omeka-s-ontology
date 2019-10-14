<?php
require __DIR__ . '/vendor/autoload.php';

use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Common\Type;
use Commando\Command;

$command = new Command();
$command->setHelp(<<<EOT
Converts a spreadsheet to JSON resource template for import into Omeka-S.

Example usage:
php resource_template.php ontology.xlsx Template > vocabulary.ttl
EOT
);

$command->option()
    ->referToAs('filename')
    ->description('Filename of spreadsheet to process. Required.');

$command->option()
    ->referToAs('template')
    ->description('Template within the speadsheet to process. Required.');

$command->option('t')
    ->alias('templates')
    ->default('Resource Templates')
    ->description(
        'Sheet which defines resource templates. Default: Resource Templates'
    );

if (empty($command[0]) || empty($command[1])) {
    $command->printHelp();
    exit;
}

switch (pathinfo($command[0], PATHINFO_EXTENSION)) {
    case 'csv':
        $type = Type::CSV;
        break;
    case 'ods':
        $type = Type::ODS;
        break;
    case 'xlsx':
    default:
        $type = Type::XLSX;
        break;
}

$reader = ReaderFactory::create($type);
$reader->open($command[0]);

foreach ($reader->getSheetIterator() as $sheet) {
    if ($sheet->getName()) {
        $sheets[$sheet->getName()] = $sheet;
    }
}

if (empty($sheets)) {
    $sheets[$command['t']] = $sheet;
}

$header = [];
$result = ['o:label' => $command[1]];
$fields = [
    'local_name',
    'label',
    'vocabulary_namespace_uri',
    'vocabulary_label',
    'o:alternate_label',
    'o:alternate_comment',
    'o:is_required',
    'o:is_private',
    'data_type_name',
    'data_type_label'
];

foreach ($sheets[$command['t']]->getRowIterator() as $row) {
    if (empty($header)) {
        $header = $row;
        continue;
    }

    $data = array_combine($header, array_pad($row, sizeof($header), ''));

    if ($data['o:label'] !== $result['o:label']) {
        continue;
    }

    $properties = [];

    foreach ($fields as $field) {
        if (in_array($field, ['o:is_required', 'o:is_private'])) {
            $properties[$field] = !empty($data[$field]);
        } elseif (!empty($data[$field])) {
            $properties[$field] = $data[$field];
        } else {
            $properties[$field] = null;
        }
    }

    if ($data['type'] === 'o:resource_class') {
        $result[$data['type']] = $properties;
    } else {
        $result[$data['type']][] = $properties;
    }
}

print(json_encode($result, JSON_PRETTY_PRINT));
