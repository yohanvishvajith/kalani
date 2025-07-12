<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReservationResource\Pages;
use App\Filament\Resources\ReservationResource\RelationManagers;
use App\Models\Reservation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Closure;
use Filament\Forms\Components\TextInput;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

class ReservationResource extends Resource
{
    protected static ?string $model = Reservation::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                
                Forms\Components\Select::make('vehicle_id')
                ->label('Select Vehicle')
                ->options(function (): array {
                    return Vehicle::where('is_available', true)
                        ->orderBy('brand')
                        ->orderBy('model')
                        ->get()
                        ->mapWithKeys(function (Vehicle $vehicle) {
                            return [
                                $vehicle->vehicle_id => "{$vehicle->brand} {$vehicle->model} (Reg: {$vehicle->registration_number})"
                            ];
                        })
                        ->toArray();
                })
                ->searchable()
                ->required()
                ->native(false)
                ->live()
                ->afterStateUpdated(function ($state, Forms\Set $set) {
                    if ($vehicle = Vehicle::find($state)) {
                        $set('daily_rate', $vehicle->daily_rate);
                    }
                }),
// In your form schema:
    DatePicker::make('start_date')
    ->label('Start Date')
    ->default(now()->toDateString())
    ->minDate(now()->toDateString())
    ->required()
    ->live()
    ->afterStateUpdated(function (Set $set) {
        $set('end_date', null);
    }),

    DatePicker::make('end_date')
    ->label('End Date')
    ->required()
    ->minDate(function (Get $get) {
        return $get('start_date') ?: now()->toDateString();
    })
    ->rules([
        function (Get $get) {
            return function (string $attribute, $value, Closure $fail) use ($get) {
                if ($get('start_date') && $value < $get('start_date')) {
                    $fail('The end date must be after the start date.');
                }
            };
        }
    ])
    ->helperText('Must be after start date')
    ->live()
    ->afterStateUpdated(function (Set $set, Get $get) {
        $startDate = $get('start_date');
        $endDate = $get('end_date');
        $dailyRate = $get('daily_rate');
    
        if ($startDate && $endDate && $dailyRate) {
            $days = (new \DateTime($endDate))->diff(new \DateTime($startDate))->days;
            $days++;
            $set('total_cost', $days * $dailyRate);
        } else {
            $set('total_cost', 0);
        }
    }),

Forms\Components\TextInput::make('total_cost')
    ->label('Total Cost')
    ->reactive()
  ,

        // Status (dropdown)
        Select::make('status')
            ->label('Status')
            ->options([
                'pending' => 'Pending',
                'confirmed' => 'Confirmed',
                'cancelled' => 'Cancelled',
            ])
            ->default('pending')
            ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
              // User ID
              TextColumn::make('user.name')
                  ->label('User Name')
                  ->sortable()
                  ->searchable(),
  
                  TextColumn::make('vehicle.brand')
                  ->label('Vehicle')
                  ->formatStateUsing(function ($state, Reservation $record) {
                      return "{$record->vehicle->brand} {$record->vehicle->model} ({$record->vehicle->registration_number})";
                  })
                  ->sortable()
                  ->searchable(),
  
              // Start Date
              TextColumn::make('start_date')
                  ->label('Start Date')
                  ->default(now()->toDateString())
                  ->date()
                  ->sortable(),
  
              // End Date
              TextColumn::make('end_date')
                  ->label('End Date')
                  ->date()
                  ->sortable(),
  
              // Total Cost
              TextColumn::make('total_cost')
                  ->label('Total Cost')
                  ->money('LKR') // Format as currency
                  ->sortable(),
  
              // Status
              TextColumn::make('status')
                  ->label('Status')
                  ->badge()
                  ->color(fn (string $state): string => match ($state) {
                      'pending' => 'warning',
                      'confirmed' => 'success',
                      'cancelled' => 'danger',
                  }),
            ])
            ->filters([
                //
                Filter::make('end_date_today')
                    ->label('Today Completed')
                    ->query(fn (Builder $query) => $query->whereDate('end_date', now()->toDateString())),
            
       
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReservations::route('/'),
            'create' => Pages\CreateReservation::route('/create'),
            'edit' => Pages\EditReservation::route('/{record}/edit'),
        ];
    }
}
