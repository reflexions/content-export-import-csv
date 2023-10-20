<?php

namespace Drupal\content_export_import_csv\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\content_export_import_csv\Controller\ContentExportController;

class ContentExportForm extends FormBase
{
    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'content_export_csv_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $export_object = new ContentExportController;

        $form['content_type_list'] = [
            '#title'=> $this->t('Content Type'),
            '#type'=> 'select',
            '#options'=> $export_object->getContentTypeOptions()
        ];

        $form['english_only'] = [
            '#title'=> $this->t('English only'),
            '#type'=> 'checkbox',
        ];

        $form['start_date'] = [
            '#title'=> $this->t('Start date'),
            '#type'=> 'date',
        ];

        $form['export'] = [
            '#value'=> 'Export',
            '#type'=> 'submit'
        ];

        return $form;
    }

    public function sputcsv($fields, $delimiter = ",", $enclosure = '"', $escape_char = "\\")
    {
        $buffer = fopen('php://temp', 'r+');
        fputcsv($buffer, $fields, $delimiter, $enclosure, $escape_char);
        rewind($buffer);
        $csv = fgets($buffer, PHP_INT_MAX);
        fclose($buffer);
        return $csv;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        global $base_url;
        $export_object = new ContentExportController;
        $submittedNodeType = $form_state->getValue('content_type_list');
        $english_only = $form_state->getValue('english_only') === 1;

        $private_path = PrivateStream::basepath();
        $public_path = PublicStream::basepath();
        $file_base = ($private_path) ? $private_path : $public_path;
        $filename = "$submittedNodeType-" . date("c") . ".csv";
        $filepath = $file_base . '/' . $filename;

        $nodeTypes = ($submittedNodeType === ContentExportController::allContentKey)
            ? array_keys(ContentExportController::getContentTypes())
            : [ $submittedNodeType ];

        $csvFile = fopen($filepath, "w");

        $columns = [];
        foreach ($nodeTypes as $nodeType) {
            $nodeKeys = ContentExportController::getExportableFieldList($nodeType);
            $newKeys = array_diff($nodeKeys, $columns);
            $columns = array_merge($columns, $newKeys);
        }
        fputcsv($csvFile, $columns);

        foreach ($nodeTypes as $nodeType) {
            $csvData = $export_object->getNodeCsvData($nodeType, $english_only);
            foreach ($csvData as $csvDataRow) {
                $blankRow = array_fill_keys($columns, null);
                $rowCells = array_merge($blankRow, $csvDataRow);
                fputcsv($csvFile, $rowCells);
            }
        }
        fclose($csvFile);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="'. basename($filepath) . '";');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        unlink($filepath);
        exit;
    }
}
