<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\HttpStatus;
use App\Enums\ImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContactImportCollection;
use App\Http\Resources\ContactImportResource;
use App\Jobs\ProcessContactImport;
use App\Models\ContactImport;
use App\Models\Workspace;
use App\Services\Audience\ContactImporter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactImportController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize('view', $workspace);

        $imports = $workspace->imports()->with('contactList')->latest('id')->paginate($request->input('limit', 30));

        return (new ContactImportCollection($imports))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    /**
     * Upload a CSV and get its headers, sample rows and a suggested mapping.
     */
    public function store(Request $request, Workspace $workspace, ContactImporter $importer)
    {
        $this->authorize('contribute', $workspace);

        $this->validate($request, [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        $path = $request->file('file')->store("imports/{$workspace->ulid}");

        $import = new ContactImport(['file_path' => $path, 'mapping' => []]);
        $import->workspace()->associate($workspace);
        $import->user()->associate($request->user());

        $reader = $importer->reader($import);

        abort_if(
            ! array_filter($reader->headers()),
            HttpStatus::UNPROCESSABLE_ENTITY->value,
            'The file needs a header row.'
        );

        $import->mapping = $importer->suggestMapping($reader->headers());
        $import->save();

        return (new ContactImportResource($import))->additional([
            'message' => 'File uploaded. Confirm the column mapping to start the import.',
            'status' => 'success',
            'status_code' => HttpStatus::CREATED,
            'headers' => $reader->headers(),
            'sample' => $reader->sample(),
            'fields' => ContactImporter::FIELDS,
        ])->response()->setStatusCode(HttpStatus::CREATED->value);
    }

    public function show(Workspace $workspace, ContactImport $import)
    {
        $this->authorize('view', $workspace);

        return (new ContactImportResource($import->load('contactList')))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    /**
     * Confirm the mapping and queue the import.
     */
    public function start(Request $request, Workspace $workspace, ContactImport $import)
    {
        $this->authorize('contribute', $workspace);

        abort_if(
            $import->status !== ImportStatus::PENDING,
            HttpStatus::CONFLICT->value,
            'This import has already started.'
        );

        $valid = $this->validate($request, [
            'mapping' => ['required', 'array'],
            'mapping.*' => ['nullable', 'string', 'max:64', 'regex:/^(email|phone|first_name|last_name|fields\.[a-z0-9_]+)$/'],
            'list' => ['nullable', 'string', Rule::exists('contact_lists', 'ulid')->where('workspace_id', $workspace->id)],
        ], [
            'mapping.*.regex' => 'Map columns to email, phone, first_name, last_name or fields.<name>.',
        ]);

        $mapped = array_filter($valid['mapping']);

        abort_unless(
            in_array('email', $mapped, true) || in_array('phone', $mapped, true),
            HttpStatus::UNPROCESSABLE_ENTITY->value,
            'Map at least one column to email or phone.'
        );

        abort_if(
            count($mapped) !== count(array_unique($mapped)),
            HttpStatus::UNPROCESSABLE_ENTITY->value,
            'Each field can only be mapped once.'
        );

        $import->mapping = $valid['mapping'];
        $import->contactList()->associate(
            isset($valid['list']) ? $workspace->lists()->where('ulid', $valid['list'])->first() : null
        );
        $import->save();

        ProcessContactImport::dispatch($import);

        return (new ContactImportResource($import->refresh()->load('contactList')))->additional([
            'message' => 'Import started.',
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }
}
