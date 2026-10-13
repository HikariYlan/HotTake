<?php

namespace App\Factory;

use App\Entity\Take;
use App\Enum\Status;
use Zenstruck\Foundry\LazyValue;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Take>
 */
final class TakeFactory extends PersistentProxyObjectFactory
{
    public function __construct()
    {
    }

    public static function class(): string
    {
        return Take::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'owner' => LazyValue::new(fn () => UserFactory::createOne()),
            'status' => self::faker()->randomElement(Status::cases()),
            'title' => self::faker()->text(150),
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(function (Take $take): void {
            if (Status::FINISHED == $take->getStatus()) {
                $take->setFinishDate(new \DateTime('+2 days'));
            }
        })
        ;
    }
}
