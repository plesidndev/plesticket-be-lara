<?php

namespace App\Services;

use App\Enums\VerificationStatus;
use App\Models\Event;
use App\Models\TicketType;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Repositories\Contracts\TicketTypeRepositoryInterface;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class TicketTypeService
{
    public function __construct(
        private readonly TicketTypeRepositoryInterface $ticketTypes,
        private readonly EventRepositoryInterface $events,
    ) {}

    /**
     * Adds a tier to an event that already exists, including one that is verified and selling.
     *
     * Verification deliberately stays where it is. Moderation covers the organizer's identity and
     * the event itself, not its price list, and returning a live event to pending would stop
     * checkout — orders are refused for anything but a verified event — until an admin looked at
     * it again. Every other route into ticket types (EventService::update) is closed on a verified
     * event, which is exactly why this one exists.
     */
    public function add(string $eventId, int $userId, array $data): TicketType
    {
        $event = $this->resolveEvent($eventId, $userId);

        return $this->ticketTypes->add($event->id, $data);
    }

    public function update(string $eventId, int $userId, int $ticketTypeId, array $data): TicketType
    {
        $event      = $this->resolveEvent($eventId, $userId);
        $ticketType = $this->resolveTicketType($event, $ticketTypeId);

        $this->assertSaleWindow($ticketType, $data);

        return $this->ticketTypes->update($ticketType, $data);
    }

    public function remove(string $eventId, int $userId, int $ticketTypeId): void
    {
        $event      = $this->resolveEvent($eventId, $userId);
        $ticketType = $this->resolveTicketType($event, $ticketTypeId);

        // order_items cascades on delete and tickets restricts, so removing a tier somebody has
        // already ordered would either erase the order line or fail at the database. Neither is a
        // thing an organizer should be able to trigger from a button.
        if ($this->ticketTypes->isReferencedByOrders($ticketType)) {
            throw new InvalidArgumentException('This ticket type has already been ordered. Deactivate it instead of deleting it.');
        }

        $this->ticketTypes->delete($ticketType);
    }

    /**
     * A partial update can carry one half of the sale window, so the incoming values are merged
     * over the stored ones before the pair is judged. Doing it here rather than in the form request
     * is what lets a caller send only sale_end and still be checked against the saved start.
     */
    private function assertSaleWindow(TicketType $ticketType, array $data): void
    {
        $start = array_key_exists('sale_start', $data) ? $data['sale_start'] : $ticketType->sale_start;
        $end   = array_key_exists('sale_end', $data) ? $data['sale_end'] : $ticketType->sale_end;

        if ($start === null || $end === null) {
            return;
        }

        if (strtotime((string) $end) < strtotime((string) $start)) {
            throw ValidationException::withMessages(['sale_end' => 'Sales must end after they start.']);
        }
    }

    private function resolveEvent(string $eventId, int $userId): Event
    {
        $event = $this->events->findById($eventId);

        // Somebody else's event answers the same way as an id that does not exist, so the endpoint
        // cannot be used to find out which events are real.
        if (! $event || $event->user_id !== $userId) {
            throw new RuntimeException('Event not found.');
        }

        if (in_array($event->verification_status, [VerificationStatus::Rejected, VerificationStatus::Suspended], true)) {
            throw new InvalidArgumentException('Ticket types cannot be changed while the event is '.$event->verification_status->value.'.');
        }

        return $event;
    }

    private function resolveTicketType(Event $event, int $ticketTypeId): TicketType
    {
        $ticketType = $this->ticketTypes->findForEvent($event->id, $ticketTypeId);

        if (! $ticketType) {
            throw new RuntimeException('Ticket type not found.');
        }

        return $ticketType;
    }
}
