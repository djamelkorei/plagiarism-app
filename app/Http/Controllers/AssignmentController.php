<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssignmentController extends Controller
{


    /**
     * Instantiate a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('permission:assignments.index')->only('index');
        $this->middleware('permission:assignments.store')->only(['create', 'store']);
        $this->middleware('permission:assignments.show')->only('show');
        $this->middleware('permission:assignments.update')->only(['edit', 'update']);
        $this->middleware('permission:assignments.destroy')->only('destroy');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('assignments');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Assignment $assignment)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Assignment $assignment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Assignment $assignment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Assignment $assignment)
    {
        //
    }
}
