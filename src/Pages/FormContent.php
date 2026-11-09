<?php

namespace Xelqun\Panels\Pages;

use Xelqun\Forms\Form;

/**
 * A form on a page, with where it submits. Put one in Page::content().
 *
 * @api
 */
final class FormContent
{
    public function __construct(
        public readonly Form $form,
        public readonly string $action,
        public readonly string $method = 'post',
        public readonly ?string $submitLabel = null,
        /** A second button that saves and starts a new one, as "Create & create another". */
        public readonly ?string $createAnotherLabel = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $schema = $this->form->toSchema();

        return [
            'type' => 'form',
            'schema' => $schema,
            'action' => $this->action,
            'method' => $this->method,
            'submitLabel' => $this->submitLabel,
            ...($this->createAnotherLabel === null ? [] : ['createAnotherLabel' => $this->createAnotherLabel]),
            // Changes after a save, so password boxes empty, but not after a failed one.
            'revision' => md5((string) json_encode([$schema['state'], $this->form->getRecord()?->getAttribute('updated_at')])),
        ];
    }
}
