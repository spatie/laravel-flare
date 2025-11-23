<?php
//This payload will be dynamically changed when executing php artisan flare:test command to users own resource data
return [
    'resourceSpans' => [
        [
            'resource' => [
                'attributes' => [
                    [
                        'key' => 'service.name',
                        'value' => [
                            'stringValue' => 'Flare Performance Test',
                        ],
                    ],
                    [
                        'key' => 'service.version',
                        'value' => [
                            'stringValue' => '036f095db44251359134d74e2594a0b63d83a980',
                        ],
                    ],
                    [
                        'key' => 'service.stage',
                        'value' => [
                            'stringValue' => 'production',
                        ],
                    ],
                    [
                        'key' => 'telemetry.sdk.language',
                        'value' => [
                            'stringValue' => 'php',
                        ],
                    ],
                    [
                        'key' => 'telemetry.sdk.name',
                        'value' => [
                            'stringValue' => 'spatie/flare-client-php',
                        ],
                    ],
                    [
                        'key' => 'telemetry.sdk.version',
                        'value' => [
                            'stringValue' => 'dev-context',
                        ],
                    ],
                    [
                        'key' => 'host.ip',
                        'value' => [
                            'stringValue' => '192.168.1.1',
                        ],
                    ],
                    [
                        'key' => 'host.name',
                        'value' => [
                            'stringValue' => '',
                        ],
                    ],
                    [
                        'key' => 'host.arch',
                        'value' => [
                            'stringValue' => 'arm64',
                        ],
                    ],
                    [
                        'key' => 'process.pid',
                        'value' => [
                            'intValue' => 32466,
                        ],
                    ],
                    [
                        'key' => 'process.executable.path',
                        'value' => [
                            'stringValue' => '/opt/homebrew/Cellar/php/8.4.13/bin/php',
                        ],
                    ],
                    [
                        'key' => 'process.command',
                        'value' => [
                            'stringValue' => 'artisan',
                        ],
                    ],
                    [
                        'key' => 'process.command_args',
                        'value' => [
                            'arrayValue' => [
                                'values' => [
                                    [
                                        'stringValue' => 'artisan',
                                    ],
                                    [
                                        'stringValue' => 'app:successful-command',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'key' => 'process.owner',
                        'value' => [
                            'stringValue' => 'this will be dynmically replaced by $resource of Flare client',
                        ],
                    ],
                    [
                        'key' => 'process.runtime.name',
                        'value' => [
                            'stringValue' => 'PHP (cli)',
                        ],
                    ],
                    [
                        'key' => 'process.runtime.version',
                        'value' => [
                            'stringValue' => '8.4.13',
                        ],
                    ],
                    [
                        'key' => 'os.type',
                        'value' => [
                            'stringValue' => 'darwin',
                        ],
                    ],
                    [
                        'key' => 'os.description',
                        'value' => [
                            'stringValue' => '25.1.0',
                        ],
                    ],
                    [
                        'key' => 'os.name',
                        'value' => [
                            'stringValue' => 'Darwin',
                        ],
                    ],
                    [
                        'key' => 'os.version',
                        'value' => [
                            'stringValue' => 'Darwin Kernel Version 25.1.0: Mon Oct 20 19:32:41 PDT 2025; root:xnu-12377.41.6~2/RELEASE_ARM64_T6000',
                        ],
                    ],
                    [
                        'key' => 'git.hash',
                        'value' => [
                            'stringValue' => '036f095db44251359134d74e2594a0b63d83a980',
                        ],
                    ],
                    [
                        'key' => 'git.message',
                        'value' => [
                            'stringValue' => 'wip',
                        ],
                    ],
                    [
                        'key' => 'git.remote',
                        'value' => [
                            'stringValue' => 'this will be dynmically replaced by $resource of Flare client',
                        ],
                    ],
                    [
                        'key' => 'git.branch',
                        'value' => [
                            'stringValue' => 'main',
                        ],
                    ],
                    [
                        'key' => 'git.is_dirty',
                        'value' => [
                            'boolValue' => true,
                        ],
                    ],
                    [
                        'key' => 'laravel.locale',
                        'value' => [
                            'stringValue' => 'en',
                        ],
                    ],
                    [
                        'key' => 'laravel.config_cached',
                        'value' => [
                            'boolValue' => false,
                        ],
                    ],
                    [
                        'key' => 'laravel.debug',
                        'value' => [
                            'boolValue' => false,
                        ],
                    ],
                    [
                        'key' => 'flare.framework.name',
                        'value' => [
                            'stringValue' => 'laravel',
                        ],
                    ],
                    [
                        'key' => 'flare.framework.version',
                        'value' => [
                            'stringValue' => '11.46.1',
                        ],
                    ],
                ],
                'droppedAttributesCount' => 0,
            ],
            'scopeSpans' => [
                [
                    'scope' => [
                        'name' => 'spatie/flare-client-php',
                        'version' => 'dev-context',
                        'attributes' => [],
                        'droppedAttributesCount' => 0,
                    ],
                    'spans' => [
                        [
                            'traceId' => 'fa0e8fede9e78f1cc222397e72008871',
                            'spanId' => 'c98fd5220c158714',
                            'parentSpanId' => null,
                            'name' => 'App - Flare Performance Test',
                            'startTimeUnixNano' => 1763643186000000000,
                            'endTimeUnixNano' => 1763643187000000000,
                            'attributes' => [
                                [
                                    'key' => 'flare.span_type',
                                    'value' => [
                                        'stringValue' => 'php_application',
                                    ],
                                ],
                            ],
                            'droppedAttributesCount' => 0,
                            'droppedEventsCount' => 0,
                            'events' => [],
                            'links' => [],
                            'droppedLinksCount' => 0,
                            'status' => [
                                'code' => 0,
                            ],
                        ],
                        [
                            'traceId' => 'fa0e8fede9e78f1cc222397e72008871',
                            'spanId' => 'c1fdef99510bd4e5',
                            'parentSpanId' => 'c98fd5220c158714',
                            'name' => 'Registering App',
                            'startTimeUnixNano' => 1763643186000000000,
                            'endTimeUnixNano' => 1763643187000000000,
                            'attributes' => [
                                [
                                    'key' => 'flare.span_type',
                                    'value' => [
                                        'stringValue' => 'php_application_registration',
                                    ],
                                ],
                            ],
                            'droppedAttributesCount' => 0,
                            'events' => [],
                            'droppedEventsCount' => 0,
                            'links' => [],
                            'droppedLinksCount' => 0,
                            'status' => [
                                'code' => 0,
                            ],
                        ],
                        [
                            'traceId' => 'fa0e8fede9e78f1cc222397e72008871',
                            'spanId' => '9e597557f940d767',
                            'parentSpanId' => 'c98fd5220c158714',
                            'name' => 'Booting App',
                            'startTimeUnixNano' => 1763643186000000000,
                            'endTimeUnixNano' => 1763643187000000000,
                            'attributes' => [
                                [
                                    'key' => 'flare.span_type',
                                    'value' => [
                                        'stringValue' => 'php_application_boot',
                                    ],
                                ],
                            ],
                            'droppedAttributesCount' => 0,
                            'events' => [],
                            'droppedEventsCount' => 0,
                            'links' => [],
                            'droppedLinksCount' => 0,
                            'status' => [
                                'code' => 0,
                            ],
                        ],
                        [
                            'traceId' => 'fa0e8fede9e78f1cc222397e72008871',
                            'spanId' => 'e89d6e695081a6f5',
                            'parentSpanId' => 'c98fd5220c158714',
                            'name' => 'Command - app:successful-command',
                            'startTimeUnixNano' => 1763643186000000000,
                            'endTimeUnixNano' => 1763643187000000000,
                            'attributes' => [
                                [
                                    'key' => 'flare.span_type',
                                    'value' => [
                                        'stringValue' => 'php_command',
                                    ],
                                ],
                                [
                                    'key' => 'process.command',
                                    'value' => [
                                        'stringValue' => 'app:successful-command',
                                    ],
                                ],
                                [
                                    'key' => 'process.command_args',
                                    'value' => [
                                        'arrayValue' => [
                                            'values' => [
                                                [
                                                    'stringValue' => 'app:successful-command',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'key' => 'process.exit_code',
                                    'value' => [
                                        'intValue' => 0,
                                    ],
                                ],
                            ],
                            'droppedAttributesCount' => 0,
                            'events' => [],
                            'droppedEventsCount' => 0,
                            'links' => [],
                            'droppedLinksCount' => 0,
                            'status' => [
                                'code' => 0,
                            ],
                        ],
                        [
                            'traceId' => 'fa0e8fede9e78f1cc222397e72008871',
                            'spanId' => '271fee7174c896b0',
                            'parentSpanId' => 'c98fd5220c158714',
                            'name' => 'Terminating App',
                            'startTimeUnixNano' => 1763643186000000000,
                            'endTimeUnixNano' => 1763643187000000000,
                            'attributes' => [
                                [
                                    'key' => 'flare.span_type',
                                    'value' => [
                                        'stringValue' => 'php_application_terminating',
                                    ],
                                ],
                            ],
                            'droppedAttributesCount' => 0,
                            'events' => [],
                            'droppedEventsCount' => 0,
                            'links' => [],
                            'droppedLinksCount' => 0,
                            'status' => [
                                'code' => 0,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
