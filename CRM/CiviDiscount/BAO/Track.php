<?php

/**
 * @package CiviDiscount
 */
class CRM_CiviDiscount_BAO_Track extends CRM_CiviDiscount_DAO_Track {

  /**
   * Add the membership log record.
   *
   * @param array $params
   *   Values to use in create.
   *
   * @return CRM_CiviDiscount_DAO_Track
   */
  public static function create($params) {
    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, 'DiscountTrack', CRM_Utils_Array::value('id', $params), $params);

    $dao = new CRM_CiviDiscount_DAO_Track();
    $dao->copyValues($params);
    $dao->save();

    if ($hook == 'create') {
      CRM_CiviDiscount_BAO_Item::incrementUsage($dao->item_id);
    }
    CRM_Utils_Hook::post($hook, 'DiscountTrack', $dao->id, $dao);
    return $dao;
  }

  /**
   * Takes a bunch of params that are needed to match certain criteria and
   * retrieves the relevant objects. Typically the valid params are only
   * contact_id. We'll tweak this function to be more full featured over a period
   * of time. This is the inverse function of create. It also stores all the retrieved
   * values in the default array
   *
   * @param array $params (reference) an assoc array of name/value pairs
   * @param array $defaults (reference) an assoc array to hold the flattened values
   *
   * @return object CRM_CiviDiscount_BAO_Item object on success, null otherwise
   * @access public
   * @static
   */
  public static function retrieve(&$params, &$defaults) {
    $item = new CRM_CiviDiscount_DAO_Track();
    $item->copyValues($params);
    if ($item->find(TRUE)) {
      CRM_Core_DAO::storeValues($item, $defaults);
      return $item;
    }
    return NULL;
  }

  public static function getUsageByContact($id) {
    return CRM_CiviDiscount_BAO_Track::getUsage(NULL, $id, NULL);
  }

  public static function getUsageByOrg($id) {
    return CRM_CiviDiscount_BAO_Track::getUsage(NULL, NULL, $id);
  }

  public static function getUsageByCode($id) {
    return CRM_CiviDiscount_BAO_Track::getUsage($id, NULL, NULL);
  }

  public static function getUsage($id = NULL, $cid = NULL, $orgid = NULL) {
    $sql = "
SELECT    t.item_id as item_id,
      t.contact_id as contact_id,
      t.used_date as used_date,
      t.contribution_id as contribution_id,
      t.entity_table as entity_table,
      t.entity_id as entity_id,
      t.description as description ";

    $from = " FROM cividiscount_track AS t ";

    if ($orgid) {
      $sql .= ", i.code ";
      $where = " LEFT JOIN cividiscount_item AS i ON (i.id = t.item_id) ";
      $where .= " WHERE i.organization_id = " . CRM_Utils_Type::escape($orgid, 'Integer');
    }
    else {
      if ($cid) {
        $sql .= ", i.code ";
        $where = " LEFT JOIN cividiscount_item AS i ON (i.id = t.item_id) ";
        $where .= " WHERE t.contact_id = " . CRM_Utils_Type::escape($cid, 'Integer');
      }
      else {
        $where = " WHERE t.item_id = " . CRM_Utils_Type::escape($id, 'Integer');
      }
    }

    $orderby = " ORDER BY t.item_id, t.used_date ";

    $sql = $sql . $from . $where . $orderby;

    $dao = new CRM_Core_DAO();
    $dao->query($sql);
    $rows = [];
    while ($dao->fetch()) {
      $row = [];
      $row['contact_id'] = $dao->contact_id;
      $row['display_name'] = CRM_Contact_BAO_Contact::displayName($dao->contact_id);
      $row['used_date'] = $dao->used_date;
      $row['contribution_id'] = $dao->contribution_id;
      $row['entity_table'] = $dao->entity_table;
      $row['entity_id'] = $dao->entity_id;
      $row['description'] = $dao->description;
      if (isset($dao->code)) {
        $row['code'] = $dao->code;
      }
      if ($row['entity_table'] == 'civicrm_participant') {
        $event_id = self::_get_participant_event($dao->entity_id);
        $events = CRM_CiviDiscount_Utils::getEvents();
        if (array_key_exists($event_id, $events)) {
          $row['event_title'] = $events[$event_id];
        }
      } elseif ($row['entity_table'] == 'civicrm_contribution_page') {
        $contribution_page = CRM_CiviDiscount_Utils::getContributionPages($dao->entity_id);
        $row['contribution_page_title'] = $contribution_page[$dao->entity_id];
      }
      else {
        if ($row['entity_table'] == 'civicrm_membership') {
          $result = CRM_Member_BAO_Membership::getStatusANDTypeValues($dao->entity_id);
          if (array_key_exists($dao->entity_id, $result)) {
            if (array_key_exists('membership_type', $result[$dao->entity_id])) {
              $row['membership_title'] = $result[$dao->entity_id]['membership_type'];
            }
          }
        }
      }
      $rows[] = $row;
    }

    return $rows;
  }

  /**
   * Look up event id from participant id.
   */
  public static function _get_participant_event($participant_id) {
    $sql = "SELECT event_id FROM civicrm_participant WHERE id = $participant_id";
    $dao =& CRM_Core_DAO::executeQuery($sql, []);
    if ($dao->fetch()) {
      return $dao->event_id;
    }

    return NULL;
  }

  /**
   * Function to delete discount codes track
   *
   * @param  int $trackID ID of the discount code track to be deleted.
   *
   * @access public
   * @static
   * @return true on success else false
   */
  public static function del($trackID) {
    if (!CRM_Utils_Rule::positiveInteger($trackID)) {
      return FALSE;
    }

    $item = new CRM_CiviDiscount_DAO_Track();
    $item->id = $trackID;
    $item->delete();

    return TRUE;
  }

}
