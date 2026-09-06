<?php

namespace App\Services\Storefront;

use App\Services\StoreSettings;

/**
 * Which storefront skin the shop is wearing.
 *
 * HQ picks this in Store Settings and the whole shop changes: a different
 * header, a different product card, a different palette and typeface. It is
 * one setting rather than a deploy, because "try the other look for the
 * campaign" is a shop decision, not an engineering one.
 *
 * What a theme owns is the chrome and the catalogue tiles — the header, the
 * navigation, the footer, the product card and the section headings — plus the
 * palette and the faces everything else inherits. The pages themselves are the
 * same components in both: a basket is a basket, and forking checkout into two
 * markups is how one of them quietly stops being tested.
 */
class Themes
{
    public const DEFAULT = 'ashion';

    public const SETTING = 'storefront_theme';

    /**
     * @return array<string, array{label: string, description: string, accent: string, css: string, fonts: string}>
     */
    public const ALL = [
        'ashion' => [
            'label' => 'Ashion',
            'description' => 'The shop as it is now. White, editorial, generous spacing, one product at a time.',
            'accent' => '#ca1515',
            'css' => 'resources/sass/storefront.scss',
            'fonts' => 'https://fonts.googleapis.com/css2?family=Cookie&family=Montserrat:wght@400;500;600;700;800;900&display=swap',
        ],
        'electro' => [
            'label' => 'Electro',
            'description' => 'A denser catalogue shop. Search and basket in the header, a category bar, badges and prices on bordered tiles.',
            'accent' => '#f28b00',
            'css' => 'resources/sass/electro.scss',
            'fonts' => 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap',
        ],
    ];

    public function __construct(private StoreSettings $settings) {}

    /** The chosen theme, or the default when the setting is unset or unknown. */
    public function current(): string
    {
        $chosen = (string) $this->settings->get(self::SETTING, self::DEFAULT);

        return array_key_exists($chosen, self::ALL) ? $chosen : self::DEFAULT;
    }

    /** @return array{label: string, description: string, accent: string, css: string, fonts: string} */
    public function currentTheme(): array
    {
        return self::ALL[$this->current()];
    }

    /** The Vite entry for the chosen theme. Only one of them is ever served. */
    public function css(): string
    {
        return $this->currentTheme()['css'];
    }

    public function fonts(): string
    {
        return $this->currentTheme()['fonts'];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    /**
     * The picker's shape, for the settings screen.
     *
     * @return list<array{key: string, label: string, description: string, accent: string}>
     */
    public static function choices(): array
    {
        $choices = [];

        foreach (self::ALL as $key => $theme) {
            $choices[] = [
                'key' => $key,
                'label' => $theme['label'],
                'description' => $theme['description'],
                'accent' => $theme['accent'],
            ];
        }

        return $choices;
    }
}
