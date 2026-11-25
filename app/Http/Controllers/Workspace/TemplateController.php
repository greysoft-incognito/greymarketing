<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\Channel;
use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\TemplateRequest;
use App\Http\Resources\TemplateCollection;
use App\Http\Resources\TemplatePreviewResource;
use App\Http\Resources\TemplateResource;
use App\Messaging\SmsSegments;
use App\Messaging\TemplateRenderer;
use App\Models\Template;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TemplateController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize('view', $workspace);

        $this->validate($request, [
            'channel' => ['nullable', Rule::enum(Channel::class)],
        ]);

        $templates = $workspace->templates()
            ->when($request->filled('channel'), fn ($query) => $query->where('channel', $request->input('channel')))
            ->latest('id')
            ->paginate($request->input('limit', 30));

        return (new TemplateCollection($templates))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    public function store(TemplateRequest $request, Workspace $workspace)
    {
        $template = $workspace->templates()->create($request->validated());

        return (new TemplateResource($template))->additional([
            'message' => 'Template created.',
            'status' => 'success',
            'status_code' => HttpStatus::CREATED,
        ])->response()->setStatusCode(HttpStatus::CREATED->value);
    }

    public function show(Workspace $workspace, Template $template)
    {
        $this->authorize('view', $workspace);

        return (new TemplateResource($template))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    public function update(TemplateRequest $request, Workspace $workspace, Template $template)
    {
        $template->update($request->validated());

        return (new TemplateResource($template))->additional([
            'message' => 'Template updated.',
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }

    public function destroy(Workspace $workspace, Template $template)
    {
        $this->authorize('contribute', $workspace);

        $template->delete();

        return (new TemplateResource($template))->additional([
            'message' => 'Template deleted.',
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }

    /**
     * Render the template for a contact, or for sample data.
     */
    public function preview(Request $request, Workspace $workspace, Template $template, TemplateRenderer $renderer)
    {
        $this->authorize('view', $workspace);

        $valid = $this->validate($request, [
            'contact' => ['nullable', 'string', Rule::exists('contacts', 'ulid')->where('workspace_id', $workspace->id)],
            'data' => ['nullable', 'array'],
        ]);

        $contact = isset($valid['contact']) ? $workspace->contacts()->firstWhere('ulid', $valid['contact']) : null;

        $data = [
            ...($valid['data'] ?? []),
            ...($contact?->mergeData() ?? []),
            'unsubscribe_url' => $contact?->unsubscribeUrl($template->channel) ?? '#unsubscribe',
        ];

        $body = $renderer->render($template->body, $data);

        $preview = [
            'subject' => $renderer->render($template->subject, $data),
            'html' => $renderer->render($template->html, $data, escape: true),
            'body' => $body,
        ];

        if ($template->channel === Channel::SMS) {
            $preview['sms'] = SmsSegments::for((string) $body)->toArray();
        }

        return (new TemplatePreviewResource($preview))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }
}
