<?php

declare(strict_types=1);

namespace Zolinga\Cron;
use InvalidArgumentException, Exception;
use Zolinga\System\Types\StatusEnum;
use Stringable, JsonSerializable;

/**
 * Class CronJob
 * 
 * Represents a cron job.
 * 
 * @property ?int $id The unique identifier if the job is already in the database.
 * @property string $uuid The event's unique UUID.
 * @property string $event The event name.
 * @property null|array $request The event JSON-serializable data.
 * @property-write int|string $start The time when the event should be executed. The strtotime() string will get convered into unix timestamp.
 * @property-read int $start The time when the event should be executed.
 * @property int|null $end The time when the event was executed.
 * @property int|null $errors The number of errors that occurred during the execution.
 * @property-write StatusEnum|string|int|null $status The status of the event. You can se string|int as value but it will always be converted to StatusEnum so you can always get StatusEnum from this property.
 * @property-read StatusEnum|null $status The status of the event. 
 * @property string|null $message The status message.
 * @property string|null $recurring The recurring time specification in strtotime() format.
 * @property int|null $lastRun The time when the event was last executed.
 * @property int|null $totalRuns The number of times the event was executed.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-13
 */
class CronJob implements Stringable, JsonSerializable
{
    final const STATUS_ERROR = StatusEnum::ERROR;
    final const STATUS_NOT_FOUND = StatusEnum::NOT_FOUND;
    final const STATUS_OK = StatusEnum::OK;
    final const STATUS_UNAUTHORIZED = StatusEnum::UNAUTHORIZED;
    final const STATUS_FORBIDDEN = StatusEnum::FORBIDDEN;
    final const STATUS_BAD_REQUEST = StatusEnum::BAD_REQUEST;
    final const STATUS_UNDETERMINED = StatusEnum::UNDETERMINED;
    final const STATUS_CONTINUE = StatusEnum::CONTINUE;
    final const STATUS_PROCESSING = StatusEnum::PROCESSING;
    final const STATUS_TIMEOUT = StatusEnum::TIMEOUT;
    final const STATUS_CONFLICT = StatusEnum::CONFLICT;
    final const STATUS_PRECONDITION_FAILED = StatusEnum::PRECONDITION_FAILED;
    final const STATUS_I_AM_A_TEAPOT = StatusEnum::I_AM_A_TEAPOT;
    final const STATUS_LOCKED = StatusEnum::LOCKED;

    /**
     * @var array<string,mixed>
     */
    private array $data = [
        'id' => null,
        'uuid' => '',
        'event' => '',
        'requestJson' => "null",
        'start' => 0,
        'end' => null,
        'errors' => 0,
        'status' => StatusEnum::UNDETERMINED,
        'message' => null,
        'recurring' => null,
        'lastRun' => null,
        'totalRuns' => 0
    ];

    /**
     * Undocumented function
     *
     * @param array<string,mixed> $data
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Getter
     *
     * @param string $name
     * @return string|integer|null|StatusEnum|array<string,mixed>
     */
    public function __get(string $name): string|int|null|StatusEnum|array
    {
        switch ($name) {
            case 'request':
                return json_decode($this->data['requestJson'], true, 512, JSON_THROW_ON_ERROR);
            default:
                return $this->data[$name];
        }
    }

    /**
     * Setter to set the DB fields.
     *
     * @param string $name
     * @param array<string,mixed>|string|integer|null|StatusEnum $value
     * @return void
     */
    public function __set(string $name, array|string|int|null|StatusEnum $value): void
    {
        switch ($name) {
                // ?int
            case 'lastRun':
            case 'id':
                if (!is_int($value) && $value !== null) {
                    throw new InvalidArgumentException("Invalid $name value: " . json_encode($value));
                }
                $this->data[$name] = (int) $value;
                break;
                // string
            case 'requestJson':
            case 'uuid':
            case 'event':
                if (!is_string($value)) {
                    throw new InvalidArgumentException("Invalid $name value: " . json_encode($value));
                }
                $this->data[$name] = (string) $value;
                break;
                // timestamp
            case 'start':
                $stamp = $value;
                if (is_string($stamp)) {
                    $stamp = strtotime($stamp) or throw new InvalidArgumentException("Invalid $name value: " . json_encode($value));
                }
                if (!is_int($stamp)) {
                    throw new InvalidArgumentException("Invalid $name value: " . json_encode($value));
                }
                $this->data[$name] = $stamp;
                break;
                // ?timestamp
            case 'end':
                $stamp = $value;
                if (is_string($stamp)) {
                    $stamp = strtotime($stamp) or throw new InvalidArgumentException("Invalid $name value: " . json_encode($value));
                }
                if (!is_int($stamp) && $stamp !== null) {
                    throw new InvalidArgumentException("Invalid $name value: " . json_encode($value));
                }
                $this->data[$name] = $stamp;
                break;
                // int
            case 'totalRuns':
            case 'errors':
                if (!is_int($value)) {
                    throw new InvalidArgumentException("Invalid $name value: " . json_encode($value));
                }
                $this->data[$name] = $value;
                break;
                // StatusEnum
            case 'status':
                if (is_string($value) || is_int($value)) {
                    $value = StatusEnum::tryFromString($value) or throw new InvalidArgumentException("Invalid $name value: " . $value);
                }
                /** @var StatusEnum $value */
                $this->data[$name] = $value;
                break;
                // ?string
            case 'message':
            case 'recurring':
                if (!is_string($value) && $value !== null) {
                    throw new InvalidArgumentException("Invalid $name value: " . json_encode($value));
                }
                $this->data[$name] = $value;
                break;
                // ?array
            case 'request':
                $this->data['requestJson'] = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                break;
            default:
                throw new InvalidArgumentException("Invalid property: $name");
        }
    }

    /**
     * Save the job as a new record in the database.
     *
     * @return void
     */
    public function create(): void
    {
        global $api;

        if ($this->id) {
            throw new Exception('The job already exists.');
        }
        $id = $api->db->expandQuery('INSERT INTO cronJobs (`??`) VALUES ("??")', array_keys($this->data), $this->data);
        if (!$id) {
            throw new Exception('Failed to create the job.');
        }
        $this->id = $id;
    }

    /**
     * Load the job from the database.
     *
     * @param string|int $uuidOrId
     * @return bool
     */
    public function load(int|string $uuidOrId): bool
    {
        global $api;

        if (is_numeric($uuidOrId)) $uuidOrId = (int) $uuidOrId;

        $field = is_int($uuidOrId) ? 'id' : 'uuid';
        $data = $api->db->query("SELECT * FROM cronJobs WHERE $field = ?", $uuidOrId)->current();
        if (!$data) {
            return false;
        }
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
        return true;
    }

    /**
     * Remove the job from database.
     *
     * @return bool
     */
    public function remove(): bool
    {
        global $api;

        if (!$this->id) {
            throw new Exception('The job does not exist yet. Did you forget to call create() first?');
        }
        $ret = $api->db->query('DELETE FROM cronJobs WHERE id = ?', $this->id);
        $this->id = null;

        return (bool) $ret;
    }

    /**
     * Update the existing record in the database.
     *
     * @return void
     */
    public function save(): void
    {
        global $api;

        if (!$this->id) {
                throw new Exception('The job does not exist yet. Did you forget to call create() first?');
        }
        $api->db->expandQuery('UPDATE cronJobs SET ?? WHERE id = ?', $this->data, $this->id);
    }


    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    public function jsonSerialize(): mixed
    {
        return $this->data;
    }

    public function __toString(): string
    {
        $info = [
            $this->id ? "#{$this->id}" : 'unsaved',
            $this->event,
            $this->status->getFriendlyName(),
        ];
        if ($this->start < time()) {
            $info[] = 'due';
        }
        if ($this->errors) {
            $info[] = "{$this->errors} error(s)";
        }
        return 'CronJob[' . implode(', ', $info) . ']';
    }
}
