<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $databases = $this->backups();
        $databases = array_reverse($databases);   // reverse the backups, so the newest one would be on top
        return response()->json(['databases' => $databases]);
    }
     private function backups()
    {
        $databases = [];
        $filesInFolder = File::files(storage_path('app/laravel'));
        foreach ($filesInFolder as $path) {
            array_push($databases,  array('name' =>  $path->getFilename() ,
            'size'  =>number_format( $path->getSize()/1024/1024,2),
            'created_at'  =>  Carbon::createFromTimestamp($path->getATime())->toDateTimeString()));
        }
        return $databases;
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

        try {
            Artisan::call('backup:run', ['--only-db' => true]);
            return response()->json(['result'   =>      'success']);
        } catch (\Exception $e) {

            return $e->getMessage();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($file_name)
    {

        $filePath = storage_path('app/laravel/' . $file_name);

        if (!Storage::disk('local')->exists('laravel/' . $file_name)) {
            abort(404); // Return a 404 Not Found response if the file doesn't exist
        }

        return response()->download($filePath);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($file_name)
    {
        $file_names = \explode(',', $file_name);

        foreach ($file_names as $row) {
            $filePath = Storage::disk('local')->path('laravel/'.$row);
            $deleted= File::delete($filePath);
        }

        if($deleted){
            return response()->json(['result'   =>      'success']);
        }else{
            return response()->json(['result'   =>      'error']);
        }
    }
}
