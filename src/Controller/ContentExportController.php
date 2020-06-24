<?php

namespace Drupal\content_export_import_csv\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use \Drupal\node\Entity\Node;

class ContentExportController extends ControllerBase
{
    /**
     * Get Content Type List
     */
    public function getContentType()
    {
        $contentTypes = \Drupal::service('entity.manager')->getStorage('node_type')->loadMultiple();
        $contentTypesList = [];
        foreach ($contentTypes as $contentType) {
            $contentTypesList[$contentType->id()] = $contentType->label();
        }
        return $contentTypesList;
    }

    /**
     * Gets NodesIds based on Node Type
     */
    public function getNodeIds($nodeType)
    {
        $entityQuery = \Drupal::entityQuery('node');
        $entityQuery->condition('status', 1);
        $entityQuery->condition('type', $nodeType);
        $entityIds = $entityQuery->execute();
        return $entityIds;
    }

    /**
     * Collects Node Data
     */
    public function getNodeDataList($entityIds, $nodeType)
    {
        $nodeData = Node::loadMultiple($entityIds);
        foreach ($nodeData as $nodeDataEach) {
            $nodeCsvData[] = $this->getNodeData($nodeDataEach, $nodeType);
        }
        return $nodeCsvData;
    }

    /**
     * Gets Valid Field List
     */
    public function getValidFieldList($nodeType)
    {
        $nodeArticleFields = \Drupal::entityManager()->getFieldDefinitions('node', $nodeType);
        $nodeFields = array_keys($nodeArticleFields);
        $unwantedFields = array(
          'comment',
          'sticky',
          'revision_default',
          'revision_translation_affected',
          'revision_timestamp',
          'revision_uid',
          'revision_log',
          'status',
          'created',
          'changed',
          'default_langcode',
          'vid',
          'uid',
          'promote',
          'publish_on',
          'unpublish_on',
          'menu_link',
          'content_translation_source',
          'content_translation_outdated',
          'path'
        );

        foreach ($unwantedFields as $unwantedField) {
            $unwantedFieldKey = array_search($unwantedField, $nodeFields);
            unset($nodeFields[$unwantedFieldKey]);
        }
        return $nodeFields;
    }

    /**
     * Gets Manipulated Node Data
     */
    public function getNodeData($nodeObject, $nodeType)
    {
        $nodeData = array();
        $nodeFields = $this->getValidFieldList($nodeType);
        foreach ($nodeFields as $nodeField) {
            $fieldData = $nodeObject->{$nodeField};
            $csvValue = isset($fieldData->value)
            ? $fieldData->value
            : (
            isset($fieldData->target_id)
            ? $fieldData->target_id
            : $fieldData->langcode
            );
            $nodeData[] = $csvValue;
        }
        return $nodeData;
    }

    /**
     * Get Node Data in CSV Format
     */
    public function getNodeCsvData($nodeType)
    {
        $entityIds = $this->getNodeIds($nodeType);
        $nodeData = $this->getNodeDataList($entityIds, $nodeType);

        return $nodeData;
    }
}
