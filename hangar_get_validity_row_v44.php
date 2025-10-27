<?php
session_start();
include_once "db_connect.php";

$userId = $_SESSION["HeliUser"];
$field = $_POST['field'] ?? '';

$validityFields = [
    'for_lic' => 'Foreign License',
    'passport' => 'Passport',
    'nal_visa' => 'National Visa',
    'us_visa' => 'USA Visa',
    'instruments' => 'Instrument Rating',
    'booklet' => 'Flight Log Book',
    'train_rec' => 'Training Records',
    'flight_train' => 'Flight Training',
    'base_check' => 'Base Check',
    'night_cur' => 'Night Currency',
    'night_check' => 'Night Check',
    'ifr_cur' => 'IFR Currency',
    'ifr_check' => 'IFR Check',
    'line_check' => 'Line Check',
    'hoist_check' => 'Hoist Check',
    'hoist_cur' => 'Hoist Currency',
    'crm' => 'CRM Certification',
    'hook' => 'Hook Operation',
    'herds' => 'HERDS Training',
    'dang_good' => 'Dangerous Goods',
    'huet' => 'HUET Certification',
    'english' => 'English Proficiency',
    'faids' => 'First Aid',
    'fire' => 'Fire Fighting',
    'avsec' => 'AVSEC Certification'
];

if (!array_key_exists($field, $validityFields)) {
    http_response_code(400);
    exit('Invalid field');
}

try {
    $stmt = $mysqli->prepare("SELECT * FROM validity WHERE pilot_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $validityData = $result->fetch_assoc() ?: [];
    $expiryDate = $validityData[$field] ?? '';
    $statusClass = ($expiryDate && $expiryDate >= date('Y-m-d')) ? 'text-success' : 'text-danger';
    $statusText = ($expiryDate && $expiryDate >= date('Y-m-d')) ? 'Valid' : 'Expired';
    $label = $validityFields[$field];
    ?>
    <tr data-field="<?= $field ?>">
        <td><strong><?= $label ?></strong></td>
        <td>
            <div class="input-group" style="width: 170px; position: relative; z-index: 1;">
                <input type="text"
                       class="form-control datepicker validity-date"
                       value="<?= $expiryDate ?>"
                       placeholder="YYYY-MM-DD">
                <span class="input-group-btn">
                    <button class="btn btn-primary save-validity" style="margin-left: 5px !important;" type="button">
                        <i class="fa fa-save"></i> Save
                    </button>
                </span>
            </div>
        </td>
        <td class="<?= $statusClass ?> status-cell"><?= $statusText ?></td>
        <td>
            <button class="btn btn-xs btn-danger remove-validity">
                <i class="fa fa-minus"></i> Remove
            </button>
        </td>
    </tr>
    <?php
} catch (Exception $e) {
    http_response_code(500);
    exit('Database error');
}
exit();