<?php
require __DIR__ . '/vendor/autoload.php';

use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Common\Type;
use Commando\Command;

$command = new Command();
$command->setHelp(<<<EOT
Converts a spreadsheet to Turtle RDF vocabulary for import into Omeka-S.

Example usage:
php vocabulary.php ontology.xlsx > vocabulary.ttl
EOT
);

$command->option()
    ->referToAs('filename')
    ->description('Filename of spreadsheet to process. Required.');

$command->option('p')
    ->alias('prefixes')
    ->default('Prefixes')
    ->description('Sheet which defines prefixes. Default: Prefixes');

$command->option('v')
    ->alias('vocabulary')
    ->default('Vocabulary')
    ->description('Sheet which defines the vocabulary. Default: Vocabulary');

if (empty($command[0])) {
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
    $sheets[$command['v']] = $sheet;
}

if (!empty($sheets[$command['p']])) {
    $header = [];

    foreach ($sheets[$command['p']]->getRowIterator() as $row) {
        if (empty($header)) {
            $header = $row;
            continue;
        }

        $data = array_combine($header, array_pad($row, sizeof($header), ''));

        print(
            '@prefix ' .
            $data['Prefix'] . ' <' . $data['URI'] . '> .' . PHP_EOL
        );
    }
}

$header = [];

foreach ($sheets[$command['v']]->getRowIterator() as $row) {
    if (empty($header)) {
        $header = $row;
        continue;
    }

    $data = array_combine($header, array_pad($row, sizeof($header), ''));

    if (empty($data['rdfs:Class'])) {
        continue;
    }

    print(PHP_EOL);

    if (empty($data['rdf:Property'])) {
        print(
            $data['rdfs:Class'] .
            ' a rdfs:Class' . PHP_EOL
        );

        if (!empty($data['rdfs:subClassOf'])) {
            print(
                '; rdfs:subClassOf ' .
                $data['rdfs:subClassOf'] . PHP_EOL
            );
        }
    } else {
        print(
            $data['rdf:Property'] .
            ' a rdf:Property' . PHP_EOL
        );

        print(
            '; rdfs:domain ' .
            $data['rdfs:Class'] . PHP_EOL
        );

        if (!empty($data['rdfs:range'])) {
            print(
                '; rdfs:range ' .
                $data['rdfs:range'] . PHP_EOL
            );
        }
    }

    if (!empty($data['rdfs:label'])) {
        print(
            '; rdfs:label ' .
            '"' . $data['rdfs:label'] . '"' . PHP_EOL
        );
    }

    if (!empty($data['rdfs:comment'])) {
        print(
            '; rdfs:comment ' .
            '"' . $data['rdfs:comment'] . '"' . PHP_EOL
        );
    }

    print('.' . PHP_EOL);
}
