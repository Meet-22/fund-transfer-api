<?php

namespace App\DataFixtures;

use App\Entity\Account;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AccountFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Create sample accounts for testing and development
        $accounts = [
            [
                'account_number' => 'ACC001234567890',
                'holder_name' => 'John Doe',
                'balance' => '5000.00',
                'currency' => 'USD',
                'status' => 'active'
            ],
            [
                'account_number' => 'ACC002345678901',
                'holder_name' => 'Jane Smith',
                'balance' => '3500.75',
                'currency' => 'USD',
                'status' => 'active'
            ],
            [
                'account_number' => 'ACC003456789012',
                'holder_name' => 'Bob Johnson',
                'balance' => '10000.00',
                'currency' => 'USD',
                'status' => 'active'
            ],
            [
                'account_number' => 'ACC004567890123',
                'holder_name' => 'Alice Brown',
                'balance' => '2500.50',
                'currency' => 'USD',
                'status' => 'active'
            ],
            [
                'account_number' => 'ACC005678901234',
                'holder_name' => 'Charlie Wilson',
                'balance' => '750.25',
                'currency' => 'USD',
                'status' => 'inactive'
            ],
            [
                'account_number' => 'ACC006789012345',
                'holder_name' => 'Diana Davis',
                'balance' => '15000.00',
                'currency' => 'USD',
                'status' => 'active'
            ],
            [
                'account_number' => 'ACC007890123456',
                'holder_name' => 'Eva Martinez',
                'balance' => '8750.80',
                'currency' => 'USD',
                'status' => 'active'
            ],
            [
                'account_number' => 'ACC008901234567',
                'holder_name' => 'Frank Garcia',
                'balance' => '0.00',
                'currency' => 'USD',
                'status' => 'frozen'
            ]
        ];

        foreach ($accounts as $accountData) {
            $account = new Account();
            $account->setAccountNumber($accountData['account_number']);
            $account->setHolderName($accountData['holder_name']);
            $account->setBalance($accountData['balance']);
            $account->setCurrency($accountData['currency']);
            $account->setStatus($accountData['status']);

            $manager->persist($account);
            
            // Add reference for use in other fixtures
            $this->addReference('account_' . $accountData['account_number'], $account);
        }

        $manager->flush();
    }
}
