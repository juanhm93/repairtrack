<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Received = 'received';
    case InReview = 'in_review';
    case InRepair = 'in_repair';
    case WaitingApproval = 'waiting_approval';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case NotRepairable = 'not_repairable';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Recibido',
            self::InReview => 'En revisión',
            self::InRepair => 'En reparación',
            self::WaitingApproval => 'Esperando aprobación/repuesto',
            self::Ready => 'Listo para entregar',
            self::Delivered => 'Entregado',
            self::NotRepairable => 'No reparable / Cancelado',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Delivered, self::NotRepairable => true,
            default => false,
        };
    }

    public function isOpen(): bool
    {
        return ! $this->isTerminal();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            self::cases(),
        );
    }
}
