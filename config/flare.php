<?php

use Spatie\ErrorSolutions\SolutionProviders\BadMethodCallSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\DefaultDbNameSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\GenericLaravelExceptionSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\IncorrectValetDbCredentialsSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\InvalidRouteActionSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\MissingAppKeySolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\MissingColumnSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\MissingImportSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\MissingLivewireComponentSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\MissingMixManifestSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\MissingViteManifestSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\OpenAiSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\RunningLaravelDuskInProductionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\SailNetworkSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\TableNotFoundSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\UndefinedViewVariableSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\UnknownMariadbCollationSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\UnknownMysql8CollationSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\UnknownValidationSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\ViewNotFoundSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\MergeConflictSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\UndefinedPropertySolutionProvider;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\FlareMiddleware\AddConsoleInformation;
use Spatie\FlareClient\FlareMiddleware\AddDumps;
use Spatie\FlareClient\FlareMiddleware\AddGitInformation;
use Spatie\FlareClient\FlareMiddleware\AddRequestInformation;
use Spatie\FlareClient\FlareMiddleware\AddSolutions;
use Spatie\FlareClient\FlareMiddleware\CensorRequestBodyFields;
use Spatie\FlareClient\FlareMiddleware\CensorRequestHeaders;
use Spatie\FlareClient\FlareMiddleware\RemoveRequestIp;
use Spatie\FlareClient\Recorders\DumpRecorder\DumpRecorder;
use Spatie\FlareClient\Recorders\ExceptionRecorder\ExceptionRecorder;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowRecorder;
use Spatie\FlareClient\Time\TimeHelper;
use Spatie\LaravelFlare\FlareMiddleware\AddExceptionHandledStatus;
use Spatie\LaravelFlare\FlareMiddleware\AddExceptionInformation;
use Spatie\LaravelFlare\FlareMiddleware\AddFailedJobInformation;
use Spatie\LaravelFlare\FlareMiddleware\AddJobs;
use Spatie\LaravelFlare\FlareMiddleware\AddLaravelContext;
use Spatie\LaravelFlare\FlareMiddleware\AddLaravelInformation;
use Spatie\LaravelFlare\FlareMiddleware\AddLogs;
use Spatie\LaravelFlare\FlareMiddleware\AddNotifierName;
use Spatie\LaravelFlare\FlareMiddleware\AddQueries;
use Spatie\LaravelFlare\Recorders\CacheRecorder\CacheRecorder;
use Spatie\LaravelFlare\Recorders\CommandRecorder\CommandRecorder;
use Spatie\LaravelFlare\Recorders\HttpRecorder\HttpRecorder;
use Spatie\LaravelFlare\Recorders\JobRecorder\FailedJobRecorder;
use Spatie\LaravelFlare\Recorders\JobRecorder\JobRecorder;
use Spatie\LaravelFlare\Recorders\LogRecorder\LogRecorder;
use Spatie\LaravelFlare\Recorders\QueryRecorder\QueryRecorder;
use Spatie\LaravelFlare\Recorders\QueueRecorder\QueueRecorder;
use Spatie\LaravelFlare\Recorders\RoutingRecorder\RoutingRecorder;
use Spatie\LaravelFlare\Recorders\TransactionRecorder\TransactionRecorder;
use Spatie\LaravelFlare\Recorders\ViewRecorder\ViewRecorder;

return [
    /*
    |
    |--------------------------------------------------------------------------
    | Flare API key
    |--------------------------------------------------------------------------
    |
    | Specify Flare's API key below to enable error reporting to the service.
    |
    | More info: https://flareapp.io/docs/flare/general/getting-started
    |
    */

    'key' => env('FLARE_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will modify the contents of the report sent to Flare.
    |
    */

    'middleware' => [
        AddRequestInformation::class => [],
        AddConsoleInformation::class => [],
        AddGitInformation::class => [],
        AddLaravelInformation::class => [],
        AddExceptionInformation::class => [],
        AddLaravelContext::class => [],
        AddExceptionHandledStatus::class => [],
        AddSolutions::class => [],
    ],

    'recorders' => [
        RoutingRecorder::class => [

        ],
        CommandRecorder::class => [
            'trace' => true,
            'report' => true,
            'max_reported' => 10,
        ],
        CacheRecorder::class => [
            'trace' => true,
            'report' => true,
            'max_reported' => 100,
            'events' => [SpanEventType::CacheHit, SpanEventType::CacheMiss, SpanEventType::CacheKeyWritten, SpanEventType::CacheKeyForgotten],
        ],
        DumpRecorder::class => [
            'trace' => false,
            'report' => true,
            'max_reported' => 25,
            'find_dump_origin' => true,
        ],
        GlowRecorder::class => [
            'trace' => true,
            'report' => true,
            'max_reported' => 100,
        ],
        QueueRecorder::class => [
            'trace' => true,
            'report' => true,
            'max_reported' => 100,
        ],
        JobRecorder::class => [
            'trace' => true,
            'report' => true,
            'max_reported' => 100,
            'max_chained_job_reporting_depth' => 2,
        ],
        HttpRecorder::class => [
            'trace' => true,
            'report' => true,
            'max_reported' => 100,
        ],
        LogRecorder::class => [
            'trace' => true,
            'report' => true,
            'max_reported' => 100,
        ],
        QueryRecorder::class => [
            'trace' => true,
            'report' => true,
            'max_reported' => 100,
            'include_bindings' => true,
            'find_origin' => true,
            'find_origin_threshold' => TimeHelper::milliseconds(700),
        ],
        TransactionRecorder::class => [
            'trace' => true,
            'report' => true,
            'max_reported' => 100,
        ],
        ViewRecorder::class => [
            'trace' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Censor data
    |--------------------------------------------------------------------------
    |
    | It is possible to censor sensitive data from the reports and sent to
    | Flare. Below you can specify which fields and header should be
    | censored. It is also possible to hide the client's IP address.
    |
    */
    'censor' => [
        'body_fields' => [
            'password',
            'password_confirmation',
        ],
        'headers' => [
            'API-KEY',
            'Authorization',
            'Cookie',
            'Set-Cookie',
            'X-CSRF-TOKEN',
            'X-XSRF-TOKEN',
        ],
        'client_ips' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporting log statements
    |--------------------------------------------------------------------------
    |
    | If this setting is `false` log statements won't be sent as events to Flare,
    | no matter which error level you specified in the Flare log channel.
    |
    */

    'send_logs_as_events' => true,

    /*
    |--------------------------------------------------------------------------
    | Solution Providers
    |--------------------------------------------------------------------------
    |
    | List of solution providers that should be loaded. You may specify additional
    | providers as fully qualified class names.
    |
    */

    'solution_providers' => [
        // from spatie/ignition
        BadMethodCallSolutionProvider::class,
        MergeConflictSolutionProvider::class,
        UndefinedPropertySolutionProvider::class,

        // from spatie/laravel-flare
        IncorrectValetDbCredentialsSolutionProvider::class,
        MissingAppKeySolutionProvider::class,
        DefaultDbNameSolutionProvider::class,
        TableNotFoundSolutionProvider::class,
        MissingImportSolutionProvider::class,
        InvalidRouteActionSolutionProvider::class,
        ViewNotFoundSolutionProvider::class,
        RunningLaravelDuskInProductionProvider::class,
        MissingColumnSolutionProvider::class,
        UnknownValidationSolutionProvider::class,
        MissingMixManifestSolutionProvider::class,
        MissingViteManifestSolutionProvider::class,
        MissingLivewireComponentSolutionProvider::class,
        UndefinedViewVariableSolutionProvider::class,
        GenericLaravelExceptionSolutionProvider::class,
        OpenAiSolutionProvider::class,
        SailNetworkSolutionProvider::class,
        UnknownMysql8CollationSolutionProvider::class,
        UnknownMariadbCollationSolutionProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Report error levels
    |--------------------------------------------------------------------------
    | When reporting errors, you can specify which error levels should be
    | reported. By default, all error levels are reported by setting
    | this value to `null`.
     */

    'report_error_levels' => null,


    /*
   |--------------------------------------------------------------------------
   | Include arguments
   |--------------------------------------------------------------------------
   |
   | Flare show you stack traces of exceptions with the arguments that were
   | passed to each method. This feature can be disabled here.
   |
   */

    'with_stack_frame_arguments' => true,

    /*
    |--------------------------------------------------------------------------
    | Force stack frame arguments ini setting
    |--------------------------------------------------------------------------
    |
    | On some machines, the `zend.exception_ignore_args` ini setting is
    | enabled by default making it impossible to get the arguments of stack
    | frames. You can force this setting to be disabled here.
    |
    */

    'force_stack_frame_arguments_ini_setting' => true,

    /*
   |--------------------------------------------------------------------------
   | Argument reducers
   |--------------------------------------------------------------------------
   |
   | Flare show you stack traces of exceptions with the arguments that were
   | passed to each method. To make these variables more readable, you can
   | specify a list of classes here which summarize the variables.
   |
   */

    'argument_reducers' => [
        \Spatie\Backtrace\Arguments\Reducers\BaseTypeArgumentReducer::class,
        \Spatie\Backtrace\Arguments\Reducers\ArrayArgumentReducer::class,
        \Spatie\Backtrace\Arguments\Reducers\StdClassArgumentReducer::class,
        \Spatie\Backtrace\Arguments\Reducers\EnumArgumentReducer::class,
        \Spatie\Backtrace\Arguments\Reducers\ClosureArgumentReducer::class,
        \Spatie\Backtrace\Arguments\Reducers\DateTimeArgumentReducer::class,
        \Spatie\Backtrace\Arguments\Reducers\DateTimeZoneArgumentReducer::class,
        \Spatie\Backtrace\Arguments\Reducers\SymphonyRequestArgumentReducer::class,
        \Spatie\LaravelFlare\ArgumentReducers\ModelArgumentReducer::class,
        \Spatie\LaravelFlare\ArgumentReducers\CollectionArgumentReducer::class,
        \Spatie\Backtrace\Arguments\Reducers\StringableArgumentReducer::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Share button
    |--------------------------------------------------------------------------
    |
    | Flare automatically adds a Share button to the laravel error page. This
    | button allows you to easily share errors with colleagues or friends. It
    | is enabled by default, but you can disable it here.
    |
    */

    'enable_share_button' => true,

    /*
    |--------------------------------------------------------------------------
    | Sender
    |--------------------------------------------------------------------------
    |
    | The sender is responsible for sending the error reports and traces to
    | Flare it can be configured if needed.
    |
    */
    'sender' => [
        'class' => \Spatie\LaravelFlare\Senders\LaravelHttpSender::class,
        'config' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracing
    |--------------------------------------------------------------------------
    |
    | Tracing allows you to see the flow of your application. It shows you
    | which parts of your application are slow and which parts are fast.
    |
    */
    'tracing' => [
        'enabled' => true,

        // The sampler is used to determine which traces should be recorded
        'sampler' => [
            'class' => \Spatie\FlareClient\Sampling\RateSampler::class,
            'config' => [
                'rate' => 1,
            ],
        ],

        // Whether to trace throwables
        'trace_throwables' => true,

        // Limits for the tracing data
        'limits' => [
            'max_spans' => 512,
            'max_attributes_per_span' => 128,
            'max_span_events_per_span' => 128,
            'max_attributes_per_span_event' => 128,
        ],
    ],

];
