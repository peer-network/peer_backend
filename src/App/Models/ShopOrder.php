<?php

declare(strict_types=1);

namespace Fawaz\App\Models;

use Fawaz\App\Models\Core\Model;
use Fawaz\App\ValidationException;
use Fawaz\Filter\PeerInputFilter;
use Fawaz\Utils\ResponseHelper;
use DateTime;

/**
 * ShopOrder stores orders made by users.
 *
 * Table: shop_orders
 *
 * Foreign Keys:
 *  1. userid -> users(uid)
 *  2. transactionoperationid -> transactions(operationid)
 *  3. shopitemid -> shop_items(uid)
 */


class ShopOrder extends Model
{
    use ResponseHelper;
    protected string $shoporderid;
    protected string $userid;
    protected string $transactionoperationid;
    protected string $shopitemid;
    protected string $name;
    protected string $email;
    protected string $addressline1;
    protected ?string $addressline2;
    protected string $city;
    protected string $zipcode;
    protected string $country;
    protected ?string $createdat;

    protected static function table(): string
    {
        return 'shop_orders';
    }

    public function __construct(array $data = [], array $elements = [], bool $validate = true)
    {
        if ($validate && !empty($data)) {
            $data = $this->validate($data, $elements);
        }

        $this->shoporderid = $data['shoporderid'] ?? self::generateUUID();
        $this->userid = $data['userid'] ?? '';
        $this->transactionoperationid = $data['transactionoperationid'] ?? '';
        $this->shopitemid = $data['shopitemid'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->email = $data['email'] ?? '';
        $this->addressline1 = $data['addressline1'] ?? '';
        $this->addressline2 = $data['addressline2'] ?? null;
        $this->city = $data['city'] ?? '';
        $this->zipcode = $data['zipcode'] ?? '';
        $this->country = $data['country'] ?? '';
        $this->createdat = $data['createdat'] ?? new DateTime()->format('Y-m-d H:i:s.u');;
    }

    public function getArrayCopy(): array
    {
        return [
            'shoporderid' => $this->shoporderid,
            'userid' => $this->userid,
            'transactionoperationid' => $this->transactionoperationid,
            'shopitemid' => $this->shopitemid,
            'name' => $this->name,
            'email' => $this->email,
            'addressline1' => $this->addressline1,
            'addressline2' => $this->addressline2,
            'city' => $this->city,
            'zipcode' => $this->zipcode,
            'country' => $this->country,
            'createdat' => $this->createdat,
        ];
    }

    public function validate(array $data, array $elements = []): array|false
    {
        $inputFilter = $this->createInputFilter($elements);
        $inputFilter->setData($data);

        if ($inputFilter->isValid()) {
            return $inputFilter->getValues();
        }

        $validationErrors = $inputFilter->getMessages();

        foreach ($validationErrors as $errors) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error;
            }
            $errorMessageString = implode("", $errorMessages);

            throw new ValidationException($errorMessageString);
        }
        return false;
    }

    protected function createInputFilter(array $elements = []): PeerInputFilter
    {
        $specification = [
            'shoporderid' => [
                'required' => false,
                'validators' => [['name' => 'Uuid']],
            ],
            'userid' => [
                'required' => true,
                'validators' => [['name' => 'Uuid']],
            ],
            'transactionoperationid' => [
                'required' => false,
                'validators' => [['name' => 'Uuid']],
            ],
            'shopitemid' => [
                'required' => false,
                'validators' => [['name' => 'Uuid']],
            ],
            'name' => [
                'required' => true,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags']],
                'validators' => [['name' => 'isString']],
            ],
            'email' => [
                'required' => true,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags']],
                'validators' => [
                    ['name' => 'EmailAddress'],
                    ['name' => 'isString'],
                ],
            ],
            'addressline1' => [
                'required' => true,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags']],
                'validators' => [['name' => 'isString']],
            ],
            'addressline2' => [
                'required' => false,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags']],
                'validators' => [['name' => 'isString']],
            ],
            'city' => [
                'required' => true,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags']],
                'validators' => [['name' => 'isString']],
            ],
            'zipcode' => [
                'required' => true,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags']],
                'validators' => [['name' => 'isString']],
            ],
            'country' => [
                'required' => true,
                'filters' => [['name' => 'StringTrim'], ['name' => 'StripTags']],
                'validators' => [['name' => 'isString']],
            ],
            'createdat' => [
                'required' => false,
                'validators' => [
                    ['name' => 'Date', 'options' => ['format' => 'Y-m-d H:i:s.u']],
                ],
            ],
        ];

        if ($elements) {
            $specification = array_filter($specification, fn ($key) => in_array($key, $elements, true), ARRAY_FILTER_USE_KEY);
        }

        return new PeerInputFilter($specification);
    }
}
