<?php

namespace App\Filament\Resources\ReportScheduleResource\Pages;

use App\Filament\Resources\ReportScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReportSchedules extends ListRecords
{
    protected static string $resource = ReportScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
