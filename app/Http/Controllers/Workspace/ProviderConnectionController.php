<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\Channel;
use App\Enums\ConnectionStatus;
use App\Enums\HttpStatus;
use App\Helpers\Provider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\ProviderConnectionRequest;
use App\Http\Resources\ProviderConnectionCollection;
use App\Http\Resources\ProviderConnectionResource;
use App\Messaging\Contracts\SendsEmail;
use App\Messaging\Contracts\SendsSms;
use App\Messaging\Data\EmailMessage;
use App\Messaging\Data\SmsMessage;
use App\Messaging\Exceptions\ProviderException;
use App\Messaging\ProviderManager;
use App\Models\ProviderConnection;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProviderConnectionController extends Controller
{
    /**
     * The providers customers can connect, with the fields each one needs.
     */
    public function catalog(ProviderManager $manager)
    {
        return Provider::response()->success(['data' => $manager->catalog()]);
    }

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize('view', $workspace);

        $this->validate($request, [
            'channel' => ['nullable', Rule::enum(Channel::class)],
        ]);

        $connections = $workspace->connections()
            ->when($request->filled('channel'), fn ($query) => $query->where('channel', $request->input('channel')))
            ->latest('id')
            ->paginate($request->input('limit', 30));

        return Provider::response()->success(new ProviderConnectionCollection($connections));
    }

    public function store(ProviderConnectionRequest $request, Workspace $workspace)
    {
        $data = $request->connectionData();

        $connection = $workspace->connections()->create($data);

        if ($connection->is_default || ! $workspace->connections()->where('channel', $connection->channel)->where('is_default', true)->exists()) {
            $connection->makeDefault();
        }

        return Provider::response()->success(new ProviderConnectionResource($connection), HttpStatus::CREATED, [
            'message' => 'Provider connected.',
        ]);
    }

    public function show(Workspace $workspace, ProviderConnection $connection)
    {
        $this->authorize('view', $workspace);

        return Provider::response()->success(new ProviderConnectionResource($connection));
    }

    public function update(ProviderConnectionRequest $request, Workspace $workspace, ProviderConnection $connection)
    {
        $data = $request->connectionData();
        $credentialsChanged = isset($data['credentials']) && $data['credentials'] !== $connection->credentials;

        $connection->fill($data);

        if ($credentialsChanged) {
            $connection->status = ConnectionStatus::UNTESTED;
            $connection->last_error = null;
        }

        $connection->save();

        if ($request->boolean('is_default')) {
            $connection->makeDefault();
        }

        return Provider::response()->success(new ProviderConnectionResource($connection), HttpStatus::ACCEPTED, [
            'message' => 'Provider connection updated.',
        ]);
    }

    public function destroy(Workspace $workspace, ProviderConnection $connection)
    {
        $this->authorize('manage', $workspace);

        $connection->delete();

        return Provider::response()->success([], HttpStatus::ACCEPTED, [
            'message' => 'Provider connection removed.',
        ]);
    }

    /**
     * Check the stored credentials against the provider.
     */
    public function test(Workspace $workspace, ProviderConnection $connection)
    {
        $this->authorize('manage', $workspace);

        try {
            $connection->driver()->verify();
            $connection->markTested();
        } catch (ProviderException $e) {
            $connection->markTested($e->getMessage());
        }

        $resource = new ProviderConnectionResource($connection);

        if ($connection->last_error) {
            return Provider::response()->error($resource, HttpStatus::UNPROCESSABLE_ENTITY, [
                'message' => $connection->last_error,
            ]);
        }

        return Provider::response()->success($resource, HttpStatus::OK, [
            'message' => 'Connection works.',
        ]);
    }

    /**
     * Send a single test message through the connection.
     */
    public function sendTest(Request $request, Workspace $workspace, ProviderConnection $connection)
    {
        $this->authorize('manage', $workspace);

        $isEmail = $connection->channel === Channel::EMAIL;

        $valid = $this->validate($request, [
            'to' => ['required', 'string', $isEmail ? 'email' : 'regex:/^\+?[1-9]\d{6,14}$/'],
            'subject' => [$isEmail ? 'required' : 'prohibited', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $driver = $connection->driver();

        try {
            $result = match (true) {
                $isEmail && $driver instanceof SendsEmail => $driver->sendEmail(
                    new EmailMessage($valid['to'], $valid['subject'], text: $valid['body'])
                ),
                ! $isEmail && $driver instanceof SendsSms => $driver->sendSms(
                    new SmsMessage($valid['to'], $valid['body'])
                ),
            };
        } catch (ProviderException $e) {
            return Provider::response()->error([], HttpStatus::UNPROCESSABLE_ENTITY, [
                'message' => $e->getMessage(),
            ]);
        }

        return Provider::response()->success(['data' => [
            'message_id' => $result->messageId,
            'status' => $result->status,
        ]], HttpStatus::OK, [
            'message' => 'Test message sent.',
        ]);
    }
}
