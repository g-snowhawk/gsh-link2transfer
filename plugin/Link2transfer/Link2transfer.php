<?php
/**
 *
 */

namespace plugin;

use Gsnowhawk\Oas\Transfer;
use Gsnowhawk\Oas\Transfer\Relational;
use Gsnowhawk\Srm;
use Gsnowhawk\Plugin;

class Link2transfer extends Plugin
{
    /**
     * Application default mode.
     */
    public const DEFAULT_MODE = Srm::DEFAULT_MODE;

    private $transfer;

    /**
     * Object Constructer.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);
    }

    public function init(): void
    {
        parent::init();
    }

    public static function extendTemplateDir(): ?string
    {
        return null;
    }



    public function beforeRendering($caller_class)
    {
        if ($caller_class === 'Gsnowhawk\\Oas\\Transfer\\Response') {
            $post = $this->caller->view->param('post');
            $format = '?mode=srm.receipt.response:download-pdf&id='.$post['issue_date'].':%d:%d';
            $receipt_id = $this->db->get(
                'id',
                'receipt_template',
                'userkey = ? AND pdf_mapper LIKE ?',
                [$this->uid, '% typeof="bill" %']
            );
            $receipts = [];
            foreach ($post['note'] ?? [] as $value) {
                // bill only
                if (preg_match('/bill:([0-9]+)(.*)/', $value ?? '', $matches)
                    && strpos($matches[2], ':received') !== 0
                ) {
                    $receipts[] = sprintf($format, $matches[1], $receipt_id);
                }
            }
            $receipts = array_unique($receipts);
            $this->caller->view->bind('receipts', $receipts);

            return;
        }

        if ($caller_class !== 'Gsnowhawk\\Srm\\Receipt\\Response') {
            return;
        }

        $post = $this->caller->view->param('post');
        $templatekey = $post['templatekey'] ?? $this->session->param('receipt_id');

        $receipt = $this->db->get(
            'title,line,pdf_mapper',
            'receipt_template',
            'id = ?',
            [$templatekey]
        );

        $current_receipt_type = null;
        if (!empty($receipt['pdf_mapper'])) {
            $pdf_mapper = simplexml_load_string($receipt['pdf_mapper']);
            $current_receipt_type = (string)$pdf_mapper->attributes()->typeof;
        }

        $draft = $post['draft'] ?? '1';

        if ($draft === '0' && !empty($current_receipt_type)) {
            $note = "{$current_receipt_type}:{$post['receipt_number']}";
            if ($current_receipt_type === 'bill') {
                $note .= ':received';
            }
            $link_count = $this->db->count(
                Transfer::TRANSFER_TABLE,
                'issue_date = ? AND note = ?',
                [$post['receipt'], $note]
            );
            if ($link_count > 0) {
                $this->caller->view->bind('linked', 'yes');
            }
        }
    }

    public function afterSaveReceipt($caller_class, $post, $type, $total_price): bool
    {
        $relation = $post['relation'] ?? 'no';
        $draft = $post['draft'] ?? null;
        if (is_null($draft)) {
            trigger_error('$_POST[\'draft\'] is not exists', E_USER_WARNING);
        }
        if ($relation !== 'yes' || $draft != '0') {
            return true;
        }

        if (method_exists($this, $type)) {
            $this->transfer = new Relational($this->caller, $this->app);
            if (false === call_user_func_array([$this, $type], [$post, $total_price])) {
                return false;
            }
        }

        return true;
    }

    private function bill($post, $total_price)
    {
        $note = 'bill:' . $post['receipt_number'];
        $category = 'T';
        $sales_amount_code = $this->db->get('item_code', 'account_items', 'userkey = ? AND system_operator = ?', [$this->uid, 'SALES']);
        $accounts_receivable_code = $this->db->get('item_code', 'account_items', 'userkey = ? AND system_operator = ?', [$this->uid, 'ACCOUNTS_RECEIVABLE']);
        $received = '';

        if (empty($post['receipt'])) {
            $item_code_left = ['1' => $accounts_receivable_code, '2' => null];
            $item_code_right = ['1' => $sales_amount_code, '2' => null];
            $datekey = 'issue_date';
        } else {
            $payment_code = $this->db->get('item_code', 'bank', 'userkey = ? AND account_number = ?', [$this->uid, $post['bank_id']]);
            if (!empty($post['cash']) || empty($payment_code)) {
                $category = 'R';
                $payment_code = $this->db->get('item_code', 'account_items', 'userkey = ? AND system_operator = ?', [$this->uid, 'CASH']);
            }
            $right_code = ($post['issue_date'] === $post['receipt'])
                ? $sales_amount_code : $accounts_receivable_code;
            $item_code_left = ['1' => $payment_code, '2' => null];
            $item_code_right = ['1' => $right_code, '2' => null];
            $this->request->param('issue_date', $post['receipt']);
            $datekey = 'receipt';
            $received = ':received';
        }

        $page_number = $this->db->get(
            'page_number',
            Transfer::TRANSFER_TABLE,
            'userkey = ? AND issue_date = ? AND category = ? AND note LIKE ?',
            [$this->uid, $post[$datekey], $category, "{$note}%"]
        );
        if (!empty($page_number)) {
            $this->request->param('page_number', $page_number);
        }

        $note .= $received;

        $this->request->param('category', $category);
        $this->request->param('amount_left', ['1' => $total_price, '2' => null]);
        $this->request->param('item_code_left', $item_code_left);
        $this->request->param('summary', ['1' => $post['subject'], '2' => $post['company']]);
        $this->request->param('item_code_right', $item_code_right);
        $this->request->param('amount_right', ['1' => $total_price, '2' => null]);
        $this->request->param('note', ['1' => $note, '2' => $note]);

        $result = $this->transfer->save();

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
    }

    private function receipt($post, $total_price)
    {
        $note = 'receipt:' . $post['receipt_number'];
        $category = 'R';
        $sales_amount_code = $this->db->get('item_code', 'account_items', 'userkey = ? AND system_operator = ?', [$this->uid, 'SALES']);
        $payment_code = $this->db->get('item_code', 'account_items', 'userkey = ? AND system_operator = ?', [$this->uid, 'CASH']);

        if (!empty($post['bank_id'])) {
            $category = 'T';
            $payment_code = $this->db->get('item_code', 'bank', 'userkey = ? AND account_number = ?', [$this->uid, $post['bank_id']]);
        }

        if (empty($post['receipt'])) {
            $post['receipt'] = $post['issue_date'];
        }
        $item_code_left = ['1' => $payment_code, '2' => null];
        $item_code_right = ['1' => $sales_amount_code, '2' => null];
        $this->request->param('issue_date', $post['receipt']);
        $datekey = 'receipt';

        $page_number = $this->db->get(
            'page_number',
            Transfer::TRANSFER_TABLE,
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

        $result = $this->transfer->save();

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
    }
}
