<?php

namespace Drupal\content_export_import_csv\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class ContentImportForm extends FormBase
{

    /**
     * Fields
     * 
     * @var array
     */
    private $fields;

    /**
     * Number of saved nodes
     * 
     * @var int
     */
    private $saved;

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

        $filename = $all_files['csv_file']->getRealPath();

        $this->importCsv($filename);

        $this->messenger()
            ->addStatus($this->t('@number translations updated/imported', ['@number' => $this->saved]));
    }

    /**
     * Import
     */
    public function importCsv(string $filename = null): bool
    {
        $handle = fopen($filename, "r");

        if ($handle === false) {
            return false;
        }

        $row = 0;

        while (($data = fgetcsv($handle, 10000, ",")) !== false) {
            $row++;
            if ($row === 1) {
                $this->readFields($data);
                continue;
            }
            $this->importEntity($data);
        }

        fclose($handle);

        return true;
    }

    private function readFields($data)
    {
        $this->fields = $data;
    }

    private function importEntity(array $data = [])
    {
        $uuid = $data[array_search('uuid', $this->fields)];
        $langcode = $data[array_search('langcode', $this->fields)];
        $data = array_combine($this->fields, $data);

        $nodes = \Drupal::entityTypeManager()
            ->getStorage('node')
            ->loadByProperties([
                'uuid' => $uuid,
            ]);

        $node = ($nodes) ? reset($nodes) : null;

        if ($node === null) {
            // If the node doesn't exist, skip
            return;
        }

        if ($node->hasTranslation($langcode)) {
            $node->removeTranslation($langcode);
        }

        $node->addTranslation($langcode, $data);
        if ($node->save()) {
            $this->saved++;
        }
    }
}
