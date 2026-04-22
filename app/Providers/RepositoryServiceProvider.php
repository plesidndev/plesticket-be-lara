<?php

namespace App\Providers;

use App\Repositories\BankRepository;
use App\Repositories\CityRepository;
use App\Repositories\Contracts\BankRepositoryInterface;
use App\Repositories\Contracts\CityRepositoryInterface;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Repositories\Contracts\OrganizerMemberRepositoryInterface;
use App\Repositories\Contracts\ProvinceRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\EventRepository;
use App\Repositories\OrganizerMemberRepository;
use App\Repositories\ProvinceRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(ProvinceRepositoryInterface::class, ProvinceRepository::class);
        $this->app->bind(CityRepositoryInterface::class, CityRepository::class);
        $this->app->bind(OrganizerMemberRepositoryInterface::class, OrganizerMemberRepository::class);
        $this->app->bind(BankRepositoryInterface::class, BankRepository::class);
        $this->app->bind(EventRepositoryInterface::class, EventRepository::class);
    }
}
