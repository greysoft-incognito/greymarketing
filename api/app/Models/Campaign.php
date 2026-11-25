<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\MessageStatus;
use App\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory;
    use HasPublicUlid;

    protected $fillable = [
        'name',
        'channel',
        'scheduled_at',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'status' => CampaignStatus::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function providerConnection(): BelongsTo
    {
        return $this->belongsTo(ProviderConnection::class, 'provider_connection_id');
    }

    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(ContactList::class, 'campaign_contact_list');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CampaignMessage::class);
    }

    /**
     * The chosen connection, or the workspace default for the channel.
     */
    public function sendingConnection(): ?ProviderConnection
    {
        return $this->providerConnection ?? $this->workspace->connections()
            ->where('channel', $this->channel)
            ->where('is_default', true)
            ->first();
    }

    /**
     * Distinct subscribed contacts across the campaign's lists.
     */
    public function audience(): Builder
    {
        return Contact::query()
            ->where('contacts.workspace_id', $this->workspace_id)
            ->subscribedTo($this->channel)
            ->whereExists(fn ($query) => $query->selectRaw('1')
                ->from('contact_list_contact')
                ->join('campaign_contact_list', 'campaign_contact_list.contact_list_id', '=', 'contact_list_contact.contact_list_id')
                ->whereColumn('contact_list_contact.contact_id', 'contacts.id')
                ->where('campaign_contact_list.campaign_id', $this->id));
    }

    /**
     * Copy the template content so later template edits do not change a sent campaign.
     */
    public function snapshotTemplate(): void
    {
        $this->forceFill([
            'subject' => $this->template->subject,
            'html' => $this->template->html,
            'body' => $this->template->body,
        ]);
    }

    /**
     * Mark the campaign completed once no message is left pending.
     */
    public function completeIfDone(): void
    {
        if ($this->messages()->where('status', MessageStatus::PENDING)->exists()) {
            return;
        }

        static::query()
            ->whereKey($this->id)
            ->where('status', CampaignStatus::SENDING)
            ->update(['status' => CampaignStatus::COMPLETED, 'completed_at' => now()]);
    }

    /**
     * Message counts per status, computed in SQL.
     *
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $counts = $this->messages()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(MessageStatus::cases())
            ->mapWithKeys(fn (MessageStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)])
            ->all();
    }
}
