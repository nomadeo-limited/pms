<?php
namespace App\FrontDesk\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FrontDeskController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'property_id' => 'required|uuid|exists:properties,id',
            'date' => 'required|date',
        ]);

        $date = $request->date;
        $propertyId = $request->property_id;
        $excluded = ['cancelled', 'no_show'];
        $with = ['customer:id,first_name,last_name,email', 'units:id,name', 'program:id,name'];

        $arrivals = Booking::where('property_id', $propertyId)
            ->where('check_in_date', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->with($with)->get();

        $departures = Booking::where('property_id', $propertyId)
            ->where('check_out_date', $date)
            ->whereNotIn('status', $excluded)
            ->with($with)->get();

        $inHouse = Booking::where('property_id', $propertyId)
            ->where('check_in_date', '<=', $date)
            ->where('check_out_date', '>', $date)
            ->where('status', 'checked_in')
            ->with($with)->get();

        return response()->json([
            'date' => $date,
            'arrivals' => $arrivals,
            'departures' => $departures,
            'in_house' => $inHouse,
        ]);
    }
}
