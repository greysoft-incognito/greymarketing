<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\Channel;
use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\ContactRequest;
use App\Http\Resources\ContactCollection;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Models\Workspace;
use App\Services\Billing\UsageLimits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize('view', $workspace);

        $this->validate($request, [
            'search' => ['nullable', 'string', 'max:100'],
            'list' => ['nullable', 'string'],
            'subscribed' => ['nullable', Rule::enum(Channel::class)],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $contacts = $workspace->contacts()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $term = '%' . mb_strtolower($request->input('search')) . '%';

                $query->where(fn (Builder $q) => $q
                    ->whereRaw('LOWER(email) LIKE ?', [$term])
                    ->orWhere('phone', 'like', $term)
                    ->orWhereRaw('LOWER(first_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$term]));
            })
            ->when($request->filled('list'), fn (Builder $query) => $query->whereHas(
                'lists',
                fn (Builder $q) => $q->where('contact_lists.ulid', $request->input('list'))
            ))
            ->when($request->filled('subscribed'), fn (Builder $query) => $query->subscribedTo(
                Channel::from($request->input('subscribed'))
            ))
            ->latest('id')
            ->paginate($request->input('limit', 30));

        return (new ContactCollection($contacts))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    public function store(ContactRequest $request, Workspace $workspace, UsageLimits $limits)
    {
        abort_if(
            $limits->contactRoom($workspace) === 0,
            HttpStatus::PAYMENT_REQUIRED->value,
            'Your plan\'s contact limit has been reached.'
        );

        $contact = $workspace->contacts()->create($request->safe()->only(['email', 'phone', 'first_name', 'last_name', 'fields']));

        $this->applyRequestState($request, $workspace, $contact);

        return (new ContactResource($contact->load('lists')))->additional([
            'message' => 'Contact created.',
            'status' => 'success',
            'status_code' => HttpStatus::CREATED,
        ])->response()->setStatusCode(HttpStatus::CREATED->value);
    }

    public function show(Workspace $workspace, Contact $contact)
    {
        $this->authorize('view', $workspace);

        return (new ContactResource($contact->load('lists')))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    public function update(ContactRequest $request, Workspace $workspace, Contact $contact)
    {
        $contact->update($request->safe()->only(['email', 'phone', 'first_name', 'last_name', 'fields']));

        $this->applyRequestState($request, $workspace, $contact);

        return (new ContactResource($contact->load('lists')))->additional([
            'message' => 'Contact updated.',
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }

    public function destroy(Workspace $workspace, Contact $contact)
    {
        $this->authorize('contribute', $workspace);

        $contact->delete();

        return (new ContactResource($contact))->additional([
            'message' => 'Contact deleted.',
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }

    /**
     * Apply subscription flags and list membership sent with the request.
     */
    protected function applyRequestState(ContactRequest $request, Workspace $workspace, Contact $contact): void
    {
        foreach (Channel::cases() as $channel) {
            $key = "{$channel->value}_subscribed";

            if ($request->has($key)) {
                $request->boolean($key) ? $contact->resubscribe($channel) : $contact->unsubscribe($channel);
            }
        }

        if ($request->has('lists')) {
            $contact->lists()->sync(
                $workspace->lists()->whereIn('ulid', $request->input('lists'))->pluck('id')
            );
        }
    }
}
