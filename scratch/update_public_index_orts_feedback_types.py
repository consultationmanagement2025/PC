import os

index_file = r'c:\xampp\htdocs\CAP101\PC\public\index.php'

with open(index_file, 'r', encoding='utf-8') as f:
    code = f.read()

old_dispatch_block = """            // Dispatch to ORTS Outbound API if ORTS Ordinance
            $ortsData = null;
            if ($isOrtsDoc) {
                if (file_exists(__DIR__ . '/../UTILS/orts_integration_utils.php')) {
                    require_once __DIR__ . '/../UTILS/orts_integration_utils.php';
                    if (function_exists('sendOrtsEvent')) {
                        $ortsPayload = [
                            'event' => 'public_feedback_received',
                            'document_id' => $consultation_id,
                            'reference_number' => $docRefNum,
                            'tracking_number' => $docRefNum,
                            'submitter_name' => $user_name,
                            'feedback_type' => $category,
                            'notes' => $message,
                            'source_system' => 'PCMS'
                        ];
                        $ortsData = sendOrtsEvent($ortsPayload);
                    }
                }
            }"""

new_dispatch_block = """            // Dispatch to ORTS Outbound API if ORTS Ordinance
            $ortsData = null;
            if ($isOrtsDoc) {
                if (file_exists(__DIR__ . '/../UTILS/orts_integration_utils.php')) {
                    require_once __DIR__ . '/../UTILS/orts_integration_utils.php';
                    
                    // Map feedback type to spec options: support | oppose | suggestion | general
                    $mappedType = 'general';
                    $catLower = strtolower($category);
                    if (strpos($catLower, 'support') !== false) {
                        $mappedType = 'support';
                    } elseif (strpos($catLower, 'oppose') !== false || strpos($catLower, 'concern') !== false || strpos($catLower, 'objection') !== false) {
                        $mappedType = 'oppose';
                    } elseif (strpos($catLower, 'suggest') !== false || strpos($catLower, 'recommend') !== false) {
                        $mappedType = 'suggestion';
                    }

                    if (function_exists('sendFeedbackToOrts')) {
                        $ortsData = sendFeedbackToOrts($consultation_id, $docRefNum, $message, $user_name, $mappedType);
                    } elseif (function_exists('sendOrtsEvent')) {
                        $ortsPayload = [
                            'event' => 'public_feedback_received',
                            'document_id' => $consultation_id,
                            'reference_number' => $docRefNum,
                            'tracking_number' => $docRefNum,
                            'submitter_name' => $user_name,
                            'feedback_type' => $mappedType,
                            'notes' => $message,
                            'source_system' => 'PCMS'
                        ];
                        $ortsData = sendOrtsEvent($ortsPayload);
                    }
                }
            }"""

if old_dispatch_block in code:
    code = code.replace(old_dispatch_block, new_dispatch_block)
    with open(index_file, 'w', encoding='utf-8') as f:
        f.write(code)
    print("Updated public/index.php dispatch block with sendFeedbackToOrts and feedback type mapping.")
else:
    print("Dispatch block already updated or pattern not found.")

