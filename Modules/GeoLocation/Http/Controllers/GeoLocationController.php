<?php

namespace Modules\GeoLocation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class GeoLocationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('geolocation::index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('geolocation::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('geolocation::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('geolocation::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id) {}
}
