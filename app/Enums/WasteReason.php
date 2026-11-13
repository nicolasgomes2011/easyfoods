<?php

namespace App\Enums;

/** Why an ingredient was thrown away (waste log). */
enum WasteReason: string
{
    case Expired         = 'expired';
    case Spoiled         = 'spoiled';
    case ImproperStorage = 'improper_storage';
    case PrepError       = 'prep_error';
    case Overproduction  = 'overproduction';
    case Other           = 'other';

    public function label(): string
    {
        return match($this) {
            self::Expired         => 'Vencido',
            self::Spoiled         => 'Estragado',
            self::ImproperStorage => 'Armazenamento incorreto',
            self::PrepError       => 'Erro de preparo',
            self::Overproduction  => 'Sobra de produção',
            self::Other           => 'Outro',
        };
    }
}
