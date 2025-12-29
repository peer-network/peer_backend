<?php

declare(strict_types=1);

namespace Fawaz\App\Models;

use Fawaz\App\Contracts\HasUserRefs;
use Fawaz\App\Profile;
use Fawaz\App\ReadModels\UserRef;

use function DI\string;

/**
 * Presentation model for a grouped transaction history item.

 */
class TransactionHistoryItem implements HasUserRefs
{
    private string $operationid;
    private string $transactiontype;
    protected ?TransactionCategory $transactioncategory;
    private string $tokenamount; // gross = net + fees total
    private string $netTokenAmount;
    private ?string $message;
    private string $createdat;
    private string $senderid;
    private string $recipientid;
    /**
     * fees = [ 'total' => float, 'burn' => ?float, 'peer' => ?float, 'inviter' => ?float ]
     */
    private array $fees;
    private ?Profile $sender = null;
    private ?Profile $recipient = null;

    public function __construct(array $data, string $currentUserId)
    {
        $tokenamount = (string)$data['tokenamount'];
        $netTokenAmount = (string)$data['netTokenAmount'];
        if( $currentUserId === (string)$data['senderid']) {
            $tokenamount = '-' . $tokenamount;
            $netTokenAmount = '-' . $netTokenAmount;
        } 

        $this->operationid = (string)($data['operationid'] ?? '');
        $this->transactiontype = (string)($data['transactiontype'] ?? '');
        $this->transactioncategory = TransactionCategory::tryFrom($data['transactioncategory']) ?? null;
        $this->tokenamount = $tokenamount;
        $this->netTokenAmount = $netTokenAmount;
        $this->message = isset($data['message']) ? (string)$data['message'] : null;
        $this->createdat = (string)($data['createdat'] ?? '');
        $this->senderid = (string)($data['senderid'] ?? '');
        $this->recipientid = (string)($data['recipientid'] ?? '');
        $this->fees = $data['fees'] ?? ['total' => '0.0', 'burn' => null, 'peer' => null, 'inviter' => null];
        // Optional pre-attached profiles if provided
        $this->sender = $data['sender'] ?? null;
        $this->recipient = $data['recipient'] ?? null;

        $this->roundTokenAmount();
    }

    // we are rounding only two values and only for presentation(api). this values are not stored anywhere
    private function roundTokenAmount() {
        $this->netTokenAmount = (string)round((float)$this->netTokenAmount, 8);
        $this->tokenamount = (string)round((float)$this->tokenamount, 8);
    }

    public function getArrayCopy(): array
    {
        return [
            'operationid' => $this->operationid,
            'transactiontype' => $this->transactiontype,
            'transactioncategory' => $this->transactioncategory->value,
            'tokenamount' => $this->tokenamount,
            'netTokenAmount' => $this->netTokenAmount,
            'message' => $this->message,
            'createdat' => $this->createdat,
            'senderid' => $this->senderid,
            'recipientid' => $this->recipientid,
            'fees' => $this->fees,
            'sender' => $this->sender ? $this->sender->getArrayCopy() : null,
            'recipient' => $this->recipient ? $this->recipient->getArrayCopy() : null,
        ];
    }

    /**
     * @return UserRef[]
     */
    public function getUserRefs(): array
    {
        $refs = [];
        if ($this->senderid !== '') {
            $refs[] = new UserRef('sender', $this->senderid);
        }
        if ($this->recipientid !== '') {
            $refs[] = new UserRef('recipient', $this->recipientid);
        }
        return $refs;
    }

    public function attachUserProfile(string $refKey, Profile $profile): void
    {
        if ($refKey === 'sender') {
            $this->sender = $profile;
        } elseif ($refKey === 'recipient') {
            $this->recipient = $profile;
        }
    }
}
