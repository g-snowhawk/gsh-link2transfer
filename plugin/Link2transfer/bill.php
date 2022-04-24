if (!isset($post['relation']) || $post['relation'] !== 'yes') {
    return;
}

$note = 'bill:' . $post['receipt_number'];
$category = 'T';
$sales_amount_code = $this->db->get('item_code', 'account_items', 'userkey = ? AND system_operator = ?', [$this->uid, 'SALES']);
$accounts_receivable_code = $this->db->get('item_code', 'account_items', 'userkey = ? AND system_operator = ?', [$this->uid, 'ACCOUNTS_RECEIVABLE']);

if (empty($post['receipt'])) {
    $item_code_left  = ['1' => $accounts_receivable_code, '2' => null];
    $item_code_right = ['1' => $sales_amount_code, '2' => null];
    $datekey = 'issue_date';
} else {
    $payment_code = $this->db->get('item_code', 'bank', 'userkey = ? AND account_number = ?', [$this->uid, $post['bank_id']]);
    if (!empty($post['cash']) || empty($payment_code)) {
        $category = 'R';
        $payment_code = $this->db->get('item_code', 'account_items', 'userkey = ? AND system_operator = ?', [$this->uid, 'CASH']);
    }
    $item_code_left  = ['1' => $payment_code, '2' => null];
    $item_code_right = ['1' => $accounts_receivable_code, '2' => null];
    $this->request->param('issue_date', $post['receipt']);
    $datekey = 'receipt';
}

$page_number = $this->db->get('page_number', Transfer::TRANSFER_TABLE,
    'userkey = ? AND issue_date = ? AND category = ? AND note = ?',
    [$this->uid, $post[$datekey], $category, $note]
);
if (!empty($page_number)) {
    $this->request->param('page_number', $page_number);
}

$this->request->param('category', $category);
$this->request->param('amount_left', ['1' => $total_price, '2' => null]);
$this->request->param('item_code_left', $item_code_left);
$this->request->param('summary', ['1' => $post['subject'], '2' => $post['company']]);
$this->request->param('item_code_right', $item_code_right);
$this->request->param('amount_right', ['1' => $total_price, '2' => null]);
$this->request->param('note', ['1' => $note, '2' => $note]);

$transfer = new Relational($this, $this->app);
$result = $transfer->save();

if (!empty($page_number)) {
    $this->request->param('page_number', null);
}
$this->request->param('category', null);
$this->request->param('amount_left', null);
$this->request->param('item_code_left', null);
$this->request->param('summary', null);
$this->request->param('item_code_right', null);
$this->request->param('amount_right', null);
$this->request->param('note', null);
$this->request->param('issue_date', $post['issue_date']);

return $result;
