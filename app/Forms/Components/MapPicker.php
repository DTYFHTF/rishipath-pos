<?php

namespace App\Forms\Components;

use Filament\Forms\Components\Field;

class MapPicker extends Field
{
    protected string $view = 'forms.components.map-picker';

    protected string | \Closure | null $mapHeight = '400px';

    protected array | \Closure $defaultLocation = [
        'lat' => 27.7172,
        'lng' => 85.3240,
    ];

    protected bool | \Closure $draggable = true;

    protected int | \Closure $defaultZoom = 13;

    protected bool | \Closure $showGeolocationButton = true;

    protected bool | \Closure $autocomplete = false;

    protected string | \Closure | null $latitudeField = null;

    protected string | \Closure | null $longitudeField = null;

    protected string | \Closure | null $addressField = null;

    protected string | \Closure | null $areaField = null;

    protected string | \Closure | null $landmarkField = null;

    protected string | \Closure | null $cityField = null;

    protected string | \Closure | null $stateField = null;

    protected string | \Closure | null $countryField = null;

    protected string | \Closure | null $pincodeField = null;

    public function mapHeight(string | \Closure $height): static
    {
        $this->mapHeight = $height;

        return $this;
    }

    public function getMapHeight(): ?string
    {
        return $this->evaluate($this->mapHeight) ?? '400px';
    }

    public function defaultLocation(array | \Closure $location): static
    {
        $this->defaultLocation = $location;

        return $this;
    }

    public function getDefaultLocation(): array
    {
        return $this->evaluate($this->defaultLocation) ?? [
            'lat' => 27.7172,
            'lng' => 85.3240,
        ];
    }

    public function draggable(bool | \Closure $draggable = true): static
    {
        $this->draggable = $draggable;

        return $this;
    }

    public function isDraggable(): bool
    {
        return $this->evaluate($this->draggable);
    }

    public function defaultZoom(int | \Closure $zoom): static
    {
        $this->defaultZoom = $zoom;

        return $this;
    }

    public function getDefaultZoom(): int
    {
        return $this->evaluate($this->defaultZoom);
    }

    public function showGeolocationButton(bool | \Closure $show = true): static
    {
        $this->showGeolocationButton = $show;

        return $this;
    }

    public function getShowGeolocationButton(): bool
    {
        return $this->evaluate($this->showGeolocationButton);
    }

    public function autocomplete(bool | \Closure $autocomplete = true): static
    {
        $this->autocomplete = $autocomplete;

        return $this;
    }

    public function getAutocomplete(): bool
    {
        return $this->evaluate($this->autocomplete);
    }

    public function reactiveFields(array $fields): static
    {
        $this->latitudeField = $fields['latitude'] ?? null;
        $this->longitudeField = $fields['longitude'] ?? null;
        $this->addressField = $fields['address'] ?? null;
        $this->areaField = $fields['area'] ?? null;
        $this->landmarkField = $fields['landmark'] ?? null;
        $this->cityField = $fields['city'] ?? null;
        $this->stateField = $fields['state'] ?? null;
        $this->countryField = $fields['country'] ?? null;
        $this->pincodeField = $fields['pincode'] ?? null;

        return $this;
    }

    public function getLatitudeField(): ?string
    {
        return $this->evaluate($this->latitudeField);
    }

    public function getLongitudeField(): ?string
    {
        return $this->evaluate($this->longitudeField);
    }

    public function getAddressField(): ?string
    {
        return $this->evaluate($this->addressField);
    }

    public function getAreaField(): ?string
    {
        return $this->evaluate($this->areaField);
    }

    public function getLandmarkField(): ?string
    {
        return $this->evaluate($this->landmarkField);
    }

    public function getCityField(): ?string
    {
        return $this->evaluate($this->cityField);
    }

    public function getStateField(): ?string
    {
        return $this->evaluate($this->stateField);
    }

    public function getCountryField(): ?string
    {
        return $this->evaluate($this->countryField);
    }

    public function getPincodeField(): ?string
    {
        return $this->evaluate($this->pincodeField);
    }
}
