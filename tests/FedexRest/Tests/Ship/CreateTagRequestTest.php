<?php declare(strict_types=1);

namespace FedexRest\Tests\Ship;

use Carbon\Carbon;
use FedexRest\Authorization\Authorize;
use FedexRest\Entity\Address;
use FedexRest\Entity\Item;
use FedexRest\Entity\Person;
use FedexRest\Entity\Weight;
use FedexRest\Exceptions\MissingAccountNumberException;
use FedexRest\Exceptions\MissingAuthCredentialsException;
use FedexRest\Services\Ship\CreateTagRequest;
use FedexRest\Services\Ship\Type\PackagingType;
use FedexRest\Services\Ship\Type\PickupType;
use FedexRest\Services\Ship\Type\ServiceType;
use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\TestCase;

class CreateTagRequestTest extends TestCase
{
    protected Authorize $auth;

    protected function setUp(): void
    {
        parent::setUp(); // TODO: Change the autogenerated stub
        $this->auth = (new Authorize)
            ->setClientId('l7749d031872cf4b55a7889376f360d045')
            ->setClientSecret('bd59d91084e8482895d4ae2fb4fb79a3');
    }

    public function testHasAccountNumber()
    {
        try {

            $request = (new CreateTagRequest)
                ->setAccessToken((string) $this->auth->authorize()->access_token)
                ->request();

        } catch (MissingAccountNumberException $e) {
            $this->assertEquals('The account number is required', $e->getMessage());
        }
    }

    public function testRequiredData()
    {

        $request = (new CreateTagRequest)
            ->setAccessToken((string) $this->auth->authorize()->access_token)
            ->setAccountNumber(740561073)
            ->setServiceType(ServiceType::_FEDEX_GROUND)
            ->setRecipients(
                (new Person)->setPersonName('Lorem')
                    ->withAddress(
                        (new Address())
                            ->setCity('Boston')
                            ->setStreetLines('line 1', 'line 2')
                    ),
                (new Person)->setPersonName('Ipsum')
            )
            ->setShipper(
                (new Person)->setPersonName('Ipsum')
            );
        $this->assertCount(2, $request->getRecipients());
        $this->assertObjectHasAttribute('personName', $request->getShipper());
        $this->assertEquals('FEDEX_GROUND', $request->getServiceType());
    }

    public function testPrepare()
    {
        $request = (new CreateTagRequest)
            ->setAccessToken((string) $this->auth->authorize()->access_token)
            ->setAccountNumber(740561073)
            ->setServiceType(ServiceType::_FEDEX_GROUND)
            ->setPackagingType(PackagingType::_YOUR_PACKAGING)
            ->setPickupType(PickupType::_DROPOFF_AT_FEDEX_LOCATION)
            ->setRecipients(
                (new Person)
                    ->setPersonName('Lorem')
                    ->setPhoneNumber('1234567890')
                    ->withAddress(
                        (new Address())
                            ->setCity('Boston')
                            ->setStreetLines('line 1', 'line 2')
                            ->setStateOrProvince('MA')
                            ->setCountryCode('US')
                            ->setPostalCode('55555')
                    )
            )
            ->setShipper(
                (new Person)
                    ->setPersonName('Ipsum')
                    ->setPhoneNumber('1234567890')
            )
            ->setLineItems((new Item())
                ->setItemDescription('lorem Ipsum')
                ->setWeight(
                    (new Weight())
                        ->setValue(1)
                        ->setUnit('LB')
                ));
        $prepared = $request->prepare();
        $this->assertEquals('Boston', $prepared['json']['requestedShipment']['recipients'][0]['address']['city']);
    }

    public function testRequest()
    {
        try {
            $request = (new CreateTagRequest())
                ->setAccessToken((string) $this->auth->authorize()->access_token)
                ->setAccountNumber(740561073)
                ->setServiceType(ServiceType::_FEDEX_GROUND)
                ->setPackagingType(PackagingType::_YOUR_PACKAGING)
                ->setPickupType(PickupType::_DROPOFF_AT_FEDEX_LOCATION)
                ->setShipDatestamp(Carbon::now()->addDays(3)->format('Y-m-d'))
                ->setShipper(
                    (new Person)
                        ->setPersonName('SHIPPER NAME')
                        ->setPhoneNumber('1234567890')
                        ->withAddress(
                            (new Address())
                                ->setCity('Collierville')
                                ->setStreetLines('RECIPIENT STREET LINE 1')
                                ->setStateOrProvince('TN')
                                ->setCountryCode('US')
                                ->setPostalCode('38017')
                        )
                )
                ->setRecipients(
                    (new Person)
                        ->setPersonName('RECEIPIENT NAME')
                        ->setPhoneNumber('1234567890')
                        ->withAddress(
                            (new Address())
                                ->setCity('Irving')
                                ->setStreetLines('RECIPIENT STREET LINE 1')
                                ->setStateOrProvince('TX')
                                ->setCountryCode('US')
                                ->setPostalCode('75063')
                        )
                )
                ->setLineItems((new Item())
                    ->setItemDescription('lorem Ipsum')
                    ->setWeight(
                        (new Weight())
                            ->setValue(1)
                            ->setUnit('LB')
                    ))
                ->request();
        } catch (MissingAccountNumberException | MissingAuthCredentialsException | GuzzleException $e) {
            $this->assertEmpty($e, sprintf('The request failed with message %s', $e->getMessage()));
        }
        $this->assertObjectHasAttribute('transactionId', $request);
        $this->assertObjectHasAttribute('encodedLabel',
            $request->output->transactionShipments[0]->pieceResponses[0]->packageDocuments[0]);
    }

}
