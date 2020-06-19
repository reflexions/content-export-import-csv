<?php

namespace Drupal\content_export_import_csv\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\content_export_import_csv\Controller\ContentImportController;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\StreamWrapper\PublicStream;

class ContentImportForm extends FormBase
{
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

        $form['file'] = array(
            '#type' => 'file',
            '#title' => 'CSV File',
            '#upload_location' => 'public://test',
            '#progress_message' => $this
              ->t('Please wait...'),
            // '#extended' => (bool) $extended,
            // '#size' => 13,
            // '#multiple' => (bool) $multiple,
          );


        // $form['content_type_list'] = [
        //     '#title'=> $this->t('Content Type'),
        //     '#type'=> 'select',
        //     '#options'=> $export_object->getContentType()
        // ];

        $form['export'] = [
            '#value'=> 'Import',
            '#type'=> 'submit'
        ];

        return $form;
    }

    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        // if (strlen($form_state->getValue('phone_number')) < 3) {
        //     $form_state->setErrorByName('phone_number', $this->t('The phone number is too short. Please enter a full phone number.'));
        // }
    }

    /**
    * {@inheritdoc}
    */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $upload = $form_state
              ->getValue('file');

    }
}
