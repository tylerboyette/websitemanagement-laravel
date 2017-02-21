<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\TicketFile;

use Auth;
use Illuminate\Support\Facades\Storage;

use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Handler\AbstractHandler;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;

class TicketFileController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Retrieve an upload.
     *
     * Uses pion/laravel-chunk-upload to parse chunk uploads.
     *
     * @return \Illuminate\Http\Response
     */
    private function processFileUpload(Request $request, $file_variable)
    {
        $file_upload = new FileReceiver($file_variable, $request, HandlerFactory::classFromRequest($request));

        // If the chunk upload was successful
        if ($file_upload->isUploaded()) {
            // Handle the chunk
            return $file_upload->receive();
        } else {
            throw new UploadMissingFileException();
        }
    }

    /**
     * Store a new file.
     *
     * @return \Illuminate\Http\Response
     */
    public function upload(Request $request)
    {
        $file = $this->processFileUpload($request, "upload_file");

        // If the file hasn't finished uploading yet...
        if (! $file->isFinished()) {
            return;
        }

        $directory = 'public/uploads/'. date('Y-m-j'). '/'. str_random(8);
        
        while (Storage::exists($directory)) {
            $directory = 'public/uploads/'. date('Y-m-j'). '/'. str_random(8);
        }

        $filename = $file->getClientOriginalName();
        $filepath = $file->storeAs($directory, $filename);

        $TicketFile = new TicketFile();
        $TicketFile->name = $filename;
        $TicketFile->path = $filepath;
        $TicketFile->url = Storage::url(preg_replace('/^public\//', '', $filepath));

        $TicketFile->user_id = Auth::user()->id;
        $TicketFile->token = str_random(60);
        $TicketFile->save();

        if ($request->wantsJson()) {
            return $TicketFile;
        }

        return redirect()->back();
    }
}