<?php

namespace Drupal\content_export_import_csv\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\node\Entity\Node;

class ContentExportController extends ControllerBase
{
    const allContentKey = 'all';

    public static $timestampFields = [
        'revision_timestamp',
        'created',
        'changed', // updated?
        'publish_on',
        'unpublish_on',
    ];

    /**
     * Get Content Type List
     */
    public static function getContentTypes()
    {
        $contentTypes = \Drupal::service('entity_type.manager')
            ->getStorage('node_type')
            ->loadMultiple();
        $contentTypesList = [];
        foreach ($contentTypes as $contentType) {
            $contentTypesList[$contentType->id()] = $contentType->label();
        }
        return $contentTypesList;
    }

    /**
     * Get Content Type List including special value 'all'
     */
    public function getContentTypeOptions()
    {
        $contentTypesList = [self::allContentKey => 'All'] + self::getContentTypes();
        return $contentTypesList;
    }

    /**
     * Gets NodesIds based on Node Type
     */
    public function getNodeIds($nodeType, bool $english_only)
    {
        $entityQuery = \Drupal::entityQuery('node')
            ->condition('status', 1, null, $english_only ? 'en' : null)
            ->condition('type', $nodeType, null, $english_only ? 'en' : null)
            ->accessCheck(true);

        $entityIds = $entityQuery->execute();
        return $entityIds;
    }

    /**
     * Collects Node Data
     */
    public function getNodeDataList($entityIds, $nodeType, bool $english_only)
    {
        $nodeData = Node::loadMultiple($entityIds);
        $nodeCsvData = array_map(
            function($value) use ($nodeType, $english_only) {
                return $this->getNodeData($value, $nodeType, $english_only);
            },
            $nodeData
        );
        return $nodeCsvData;
    }

    public static function getExportableFieldList($nodeType)
    {
        $nodeFieldDefinitions = \Drupal::service('entity_field.manager')
            ->getFieldDefinitions('node', $nodeType);
        $nodeFields = array_keys($nodeFieldDefinitions);
        $unwantedFields = array(
          'comment',
          'sticky',
          'revision_default',
          'revision_translation_affected',
//          'revision_timestamp',
//          'revision_uid',
          'revision_log',
          'status',
//          'created',
//          'changed',
          'default_langcode',
          'vid',
          'uid',
          'promote',
//          'publish_on',
//          'unpublish_on',
          'menu_link',
          'content_translation_source',
          'content_translation_outdated',
//          'path'
        );

        $wantedFields = array_diff($nodeFields, $unwantedFields);

        return $wantedFields;
    }

    public static function getImportableFieldList($nodeType)
    {
        $exportableFields = self::getExportableFieldList($nodeType);
        $unwantedFields = [
            'nid',
            'uuid',
            'changed',
            'type',
            'path',
            'revision_timestamp',
            'revision_uid',
        ];

        $wantedFields = array_diff($exportableFields, $unwantedFields);

        return $wantedFields;
    }

    /**
     * Gets Manipulated Node Data
     */
    public function getNodeData($nodeObject, $nodeType, bool $english_only)
    {
        $nodeFields = self::getExportableFieldList($nodeType);

        $nodeData = array_reduce(
            $nodeFields,
            function($carry, $nodeField) use ($nodeObject) {
                $fieldData = $nodeObject->{$nodeField};
                $csvValue = $fieldData->value
                    //?? $fieldData->target_id
                    ;

                if (in_array($nodeField, self::$timestampFields)) {
                    $csvValue = date('c', $csvValue);
                }

                $carry[ $nodeField ] = $csvValue;
                return $carry;
            },
            []
        );

        return $nodeData;
    }

    /**
     * Get Node Data in CSV Format
     */
    public function getNodeCsvData($nodeType, bool $english_only)
    {
        $entityIds = $this->getNodeIds($nodeType, $english_only);
        $nodeData = $this->getNodeDataList($entityIds, $nodeType, $english_only);

        return $nodeData;
    }
}
