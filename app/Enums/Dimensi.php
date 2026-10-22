<?php

namespace App\Enums;

/**
 * Enam dimensi Smart City. Nilai disimpan persis seperti di CSV seed
 * (typo asli "Environtment" sudah dikoreksi menjadi "Environment").
 */
enum Dimensi: string
{
    case Governance = 'Smart Governance';
    case Branding = 'Smart Branding';
    case Economy = 'Smart Economy';
    case Living = 'Smart Living';
    case Society = 'Smart Society';
    case Environment = 'Smart Environment';

    public function label(): string
    {
        return $this->value;
    }

    /**
     * Nama pendek tanpa awalan "Smart", untuk legend chart di dashboard.
     */
    public function shortLabel(): string
    {
        return $this->name;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
