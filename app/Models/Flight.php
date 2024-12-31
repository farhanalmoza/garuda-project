<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Flight extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'flight_number',
        'airline_id',
    ];

    public function airline()
    {
        return $this->belongsTo(Airline::class);
    }

    public function flightSegments()
    {
        return $this->hasMany(FlightSegment::class);
    }

    public function flightClasses()
    {
        return $this->hasMany(FlightClass::class);
    }

    public function flightSeats()
    {
        return $this->hasMany(FlightSeat::class);
    }

    public function flightTransactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function generateSeats()
    {
        $classes = $this->flightClasses;

        foreach ($classes as $class) {
            $totalSeats = $class->total_seats;
            $seatsPerRow = $this->getSeatsPerRow($class->class_type);
            $rows = ceil($totalSeats / $seatsPerRow);

            $existingSeats = FlightSeat::where('flight_id', $this->id)
                ->where('class_type', $class->class_type)
                ->get();

            $existingRows = $existingSeats->pluck('row')->toArray();

            $seatCounter = 1;

            for ($row=1; $row <= $rows; $row++) { 
                if (!in_array($row, $existingRows)) {
                    for ($column=1; $column <= $seatsPerRow; $column++) { 
                        if ($seatCounter > $totalSeats) {
                            break;
                        }

                        $seatCode = $this->generateSeatCode($row, $column);

                        FlightSeat::create([
                            'flight_id' => $this->id,
                            'name' => $seatCode,
                            'row' => $row,
                            'column' => $column,
                            'class_type' => $class->class_type,
                        ]);

                        $seatCounter++;
                    }
                }
            }

            foreach ($existingSeats as $existingSeat) {
                if ($existingSeat->column > $seatsPerRow || $existingSeat->row > $rows) {
                    $existingSeat->is_available = false;
                    $existingSeat->save();
                }
            }
        }
    }

    private function getSeatsPerRow($classType)
    {
        switch ($classType) {
            case 'business':
                return 4;
            case 'economy':
                return 6;
            default:
                return 4;
        }
    }

    private function generateSeatCode($row, $column)
    {
        $rowLetter = chr(64 + $row);

        return $rowLetter . $column;
    }
}
