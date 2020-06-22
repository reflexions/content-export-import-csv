<?php

namespace Drupal\content_export_import_csv\Form;

use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\content_export_import_csv\Controller\ContentImportController;

class ContentImportForm extends FormBase
{

    private $fields;

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
        $export_object = new ContentImportController;

        // $upload_validators = [
        //     'file_validate_extensions' => ['csv'],
        //     'file_validate_size' => [104857600], // 100MB
        // ];


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

    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        // if ($form_state->getValue('csv_file') == null) {
        //     $form_state->setErrorByName('csv_file', $this->t('Please select a CSV file to upload.'));
        // }

        // $this->messenger()->addStatus($this->t('Your phone number is @number', ['@number' => $form_state->getValue('phone_number')]));

        // $all_files = $this->getRequest()->files->get('files', []);
        // if (!empty($all_files['myfile'])) {
        //     $file_upload = $all_files['myfile'];
        //     if ($file_upload->isValid()) {
        //         $form_state->setValue('myfile', $file_upload->getRealPath());
        //         return;
        //     }
        // }

        // $form_state->setErrorByName('myfile', $this->t('The file could not be uploaded.'));
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
            ->addStatus($this->t('Records updated/imported @number', ['@number' => 6]));
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
            

            // $num = count($data);
            // echo "<p> $num fields in line $row: <br /></p>\n";
            // $row++;
            // for ($c=0; $c < $num; $c++) {
            //     echo $data[$c] . "<br />\n";
            // }
        }

        fclose($handle);

        return true;
    }

    private function readFields($data)
    {
        $this->fields = array_flip($data);
    }

    private function importEntity(array $data = [])
    {
        $uuid = $data[$this->fields['uuid']];
        $type = $data[$this->fields['type']];
        $title = $data[$this->fields['title']];

        $node = \Drupal::entityTypeManager()
            ->getStorage('node')
            ->loadByProperties(['uuid' => $uuid]);

        $node->set('title', $title);
        $node->save();

        // Create node object with attached file.
        $node = Node::create([
            'type' => $type,
            'title' => $title,
            // 'field_image' => [
            //     'target_id' => $file->id(),
            //     'alt' => 'Hello world',
            //     'title' => 'Goodbye world'
            // ],
        ]);
        $node->save();
    }
}
