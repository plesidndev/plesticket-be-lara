<?php

namespace App\Providers;

use App\Repositories\BankRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\CityRepository;
use App\Repositories\Contracts\BankRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\CityRepositoryInterface;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\OrganizerMemberRepositoryInterface;
use App\Repositories\Contracts\TicketRepositoryInterface;
use App\Repositories\Contracts\TicketTypeRepositoryInterface;
use App\Repositories\TicketTypeRepository;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\WebhookDeliveryRepositoryInterface;
use App\Repositories\Contracts\ProvinceRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\EventRepository;
use App\Repositories\OrderRepository;
use App\Repositories\OrganizerMemberRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\WebhookDeliveryRepository;
use App\Repositories\ProvinceRepository;
use App\Repositories\TicketRepository;
use App\Repositories\UserRepository;
use App\Services\Payments\Gateways\XenditGateway;
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
        $this->app->bind(CategoryRepositoryInterface::class, CategoryRepository::class);
        $this->app->bind(EventRepositoryInterface::class, EventRepository::class);
        $this->app->bind(TicketTypeRepositoryInterface::class, TicketTypeRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
        $this->app->bind(TicketRepositoryInterface::class, TicketRepository::class);
        $this->app->bind(\App\Repositories\Contracts\TalentRepositoryInterface::class, \App\Repositories\TalentRepository::class);
        $this->app->bind(PaymentRepositoryInterface::class, PaymentRepository::class);
        $this->app->bind(WebhookDeliveryRepositoryInterface::class, WebhookDeliveryRepository::class);

        // Gateways take their credentials as constructor scalars, so they are
        // built explicitly rather than autowired.
        $this->app->singleton(XenditGateway::class, fn() => new XenditGateway(
            secretKey:     config('services.xendit.secret_key'),
            callbackToken: config('services.xendit.callback_token'),
            baseUrl:       config('services.xendit.base_url'),
            timeout:       (int) config('services.xendit.timeout'),
            apiVersion:    (string) config('services.xendit.api_version'),
        ));
    }
}
