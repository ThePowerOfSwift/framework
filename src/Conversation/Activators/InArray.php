<?php

declare(strict_types=1);

namespace FondBot\Conversation\Activators;

use Illuminate\Support\Collection;
use FondBot\Events\MessageReceived;
use FondBot\Contracts\Conversation\Activator;

class InArray implements Activator
{
    protected $values;
    protected $strict;

    /**
     * InArray constructor.
     *
     * @param array|Collection $values
     * @param bool             $strict
     */
    public function __construct($values, bool $strict = true)
    {
        if (!is_array($values) && !$values instanceof Collection) {
            $values = str_getcsv($values);
        }

        $this->values = $values;
        $this->strict = $strict;
    }

    public function strict(bool $strict): self
    {
        $this->strict = $strict;

        return $this;
    }

    /**
     * Result of matching activator.
     *
     * @param MessageReceived $message
     *
     * @return bool
     */
    public function matches(MessageReceived $message): bool
    {
        $haystack = $this->values;

        if ($haystack instanceof Collection) {
            $haystack = $haystack->toArray();
        }

        return in_array($message->getText(), $haystack, $this->strict);
    }
}
