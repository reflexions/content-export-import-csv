<?php

namespace Drupal\content_export_import_csv\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\content_export_import_csv\Controller\ContentExportController;
use Drupal\node\Entity\Node;

class ContentImportForm extends FormBase
{
    /**
     * Number of saved nodes
     * 
     * @var int
     */
    private $saved = 0;

    /**
    * {@inheritdoc}
    */
    public function getFormId()
    {
        return 'content_import_csv_form';
    }

    /**
    * {@inheritdoc}
    */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form['csv_file'] = [
            '#title' => $this->t('CSV file'),
            '#description' => t('Upload CSV format only'),
            '#type' => 'file'
          ];

        $form['english_only'] = [
            '#title'=> $this->t('English only'),
            '#type'=> 'checkbox',
        ];

        $form['newer_only'] = [
            '#title'=> $this->t('Newer only'),
            '#type'=> 'checkbox',
            '#default_value' => true,
        ];

        $form['start_date'] = [
            '#title'=> $this->t('Start date'),
            '#type'=> 'date',
        ];

        $form['actions']['#type'] = 'actions';

        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t("Import"),
            '#button_type' => 'primary',
        ];

        return $form;
    }

    /**
    * {@inheritdoc}
    */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $all_files = \Drupal::request()->files->get('files', []);
        $csv_file = $all_files['csv_file'];
        if (!$csv_file) {
            $this->messenger()
                ->addError($this->t('File not specified'));
            return;
        }

        $english_only = $form_state->getValue('english_only') === 1;
        $newer_only = $form_state->getValue('newer_only') === 1;
        $start_date_str = $form_state->getValue('start_date');
        $start_date = $start_date_str ? new \DateTimeImmutable($start_date_str) : null;

        $filename = $csv_file->getRealPath();

        $this->importCsv($filename, $english_only, $newer_only, $start_date);

        $this->messenger()
            ->addStatus($this->t('@number translations updated/imported', ['@number' => $this->saved]));
    }

    /**
     * Import
     */
    public function importCsv(
        string $filename,
        bool $english_only,
        bool $newer_only,
        ?\DateTimeImmutable $start_date
    ): bool
    {
        $handle = fopen($filename, "r");

        if ($handle === false) {
            return false;
        }

        $csvColumnNames = null;
        while (($csvRowData = fgetcsv($handle)) !== false) {
            if ($csvColumnNames === null) {
                $csvColumnNames = $csvRowData;
            }
            else {
                $this->importEntity(
                    $csvColumnNames,
                    $csvRowData,
                    $english_only,
                    $newer_only,
                    $start_date
                );
            }
        }

        fclose($handle);

        return true;
    }

    private function readFields($data)
    {
        $this->csvColumnNames = $data;
    }

    /**
     * Converts iso8601 fields from csv to unix timestamp for drupal db
     * @param array $keyedRow
     * @return array
     */
    private static function transformDateFields(array $keyedRow)
    {
        foreach (ContentExportController::$timestampFields as $field) {
            if (!empty($keyedRow[$field])) {
                $keyedRow[$field] = strtotime($keyedRow[$field]);
            }
        }
        return $keyedRow;
    }

    private function importEntity(
        array $csvColumnNames,
        array $csvRowData,
        bool $english_only,
        bool $newer_only,
        ?\DateTimeImmutable $start_date
    )
    {
        $keyedRow = array_combine($csvColumnNames, $csvRowData);

        $uuid = $keyedRow['uuid'];
        $langcode = $keyedRow['langcode'];
        $node_type = $keyedRow['type'];

        if ($english_only && $langcode !== 'en') {
            // Only importing english but this isn't; skip
            return;
        }

        $csvRevisionDate = new \DateTimeImmutable($keyedRow['revision_timestamp']);

        $keyedRow = self::transformDateFields($keyedRow);

        if ($start_date && $csvRevisionDate < $start_date) {
            // the csv version is older than the requested start_date
            return;
        }

        $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');

        $nodes = $nodeStorage
            ->loadByProperties([
                'uuid' => $uuid,
            ]);

        $node = ($nodes) ? reset($nodes) : null;

        if ($node === null) {
            // If the node doesn't exist, create
            $node = Node::create(['type' => $keyedRow['type']]);
        }

        $nodeRevisionDate = (new \DateTimeImmutable())->setTimestamp($node->getRevisionCreationTime());
        if ($newer_only && $nodeRevisionDate >= $csvRevisionDate) {
            // Only importing items that are newer than the db node
            return;
        }

        //$node->addTranslation($langcode, $csvRowData);
        $revision = $node->getTranslation($langcode);
        $revision->setRevisionCreationTime($csvRevisionDate->getTimestamp());
        $revision = $nodeStorage->createRevision($revision);

        $dirty = false;
        foreach (ContentExportController::getImportableFieldList($node_type) as $field) {
            $dbValue = $revision->{$field}->value;
            $csvValue = $keyedRow[$field];
            if ($dbValue != $csvValue) {
                $revision->set($field, $csvValue);
                $dirty = true;
            }
        }

        if ($dirty) {
            if ($nodeStorage->save($revision)) {
                $this->saved++;
            }
        }
    }
}
