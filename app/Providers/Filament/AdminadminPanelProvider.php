<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminadminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('Rishipath POS')
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
                \App\Filament\Pages\AgentEarningsSettlement::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                \App\Filament\Widgets\POSStatsWidget::class,
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): string => Blade::render('@livewire(\'organization-switcher\') <div class="mx-2 h-6 w-px bg-gray-300 dark:bg-gray-600"></div> @livewire(\'store-switcher\')'),
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => view('components.topbar-navigation')->render(),
            )
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => Blade::render(<<<'BLADE'
                    <style>
                        /* Let the main content column shrink to fit beside the sticky
                           desktop sidebar. .fi-main-ctn is a flex item with the browser
                           default min-width:auto, so on pages with a wide table it refuses
                           to shrink below the table's width and renders on top of the
                           sidebar. min-width:0 lets flexbox size it correctly; the table
                           then scrolls inside its own container instead of overflowing.
                           Applies at all breakpoints: below lg (1024px) Filament already
                           renders the sidebar as an off-canvas overlay (translate-x-full
                           when closed), so there's no separate mobile overlap to fix here
                           — forcing `.fi-sidebar { display: none }` for that range (as this
                           hook used to) fights the Alpine translate classes the hamburger
                           button relies on and leaves the drawer permanently hidden. */
                        .fi-main-ctn {
                            min-width: 0 !important;
                        }
                    </style>
                BLADE),
            )
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): string => Blade::render(<<<'BLADE'
                    <script>
                        // iOS WebKit (Safari, and Brave/Chrome on iOS which use the same
                        // engine) doesn't reliably fire an `input` event when a field is
                        // filled via the QuickType/AutoFill suggestion bar. Livewire's
                        // wire:model only syncs on that event, so the browser shows the
                        // autofilled value but Livewire's component state stays empty,
                        // and submitting trips "field is required". Re-dispatch input and
                        // change events for every field right before Livewire's own
                        // wire:submit handler runs, so the synced value makes it into the
                        // request. Capture phase guarantees this runs first.
                        document.addEventListener('submit', function (event) {
                            var form = event.target;
                            if (!(form instanceof HTMLFormElement)) {
                                return;
                            }

                            form.querySelectorAll('input, textarea, select').forEach(function (field) {
                                field.dispatchEvent(new Event('input', {bubbles: true}));
                                field.dispatchEvent(new Event('change', {bubbles: true}));
                            });
                        }, true);
                    </script>
                BLADE),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\InitializeOrganizationContext::class,
            ]);
    }
}
