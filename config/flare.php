<?php

use Spatie\FlareClient\FlareMiddleware\AddGitInformation;
use Spatie\FlareClient\FlareMiddleware\RemoveRequestIp;
use Spatie\FlareClient\FlareMiddleware\CensorRequestBodyFields;
use Spatie\FlareClient\FlareMiddleware\CensorRequestHeaders;
use Spatie\Ignition\Solutions\SolutionProviders\BadMethodCallSolutionProvider;
use Spatie\Ignition\Solutions\SolutionProviders\MergeConflictSolutionProvider;
use Spatie\Ignition\Solutions\SolutionProviders\UndefinedPropertySolutionProvider;
use Spatie\LaravelFlare\FlareMiddleware\AddDumps;
use Spatie\LaravelFlare\FlareMiddleware\AddEnvironmentInformation;
use Spatie\LaravelFlare\FlareMiddleware\AddExceptionHandledStatus;
use Spatie\LaravelFlare\FlareMiddleware\AddExceptionInformation;
use Spatie\LaravelFlare\FlareMiddleware\AddJobs;
use Spatie\LaravelFlare\FlareMiddleware\AddLogs;
use Spatie\LaravelFlare\FlareMiddleware\AddQueries;
use Spatie\LaravelFlare\FlareMiddleware\AddContext;
use Spatie\LaravelFlare\FlareMiddleware\AddNotifierName;
use Spatie\LaravelFlare\Recorders\DumpRecorder\DumpRecorder;
use Spatie\LaravelFlare\Recorders\JobRecorder\JobRecorder;
use Spatie\LaravelFlare\Recorders\LogRecorder\LogRecorder;
use Spatie\LaravelFlare\Recorders\QueryRecorder\QueryRecorder;
use Spatie\LaravelFlare\Solutions\SolutionProviders\DefaultDbNameSolutionProvider;
use Spatie\LaravelFlare\Solutions\SolutionProviders\GenericLaravelExceptionSolutionProvider;
use Spatie\LaravelFlare\Solutions\SolutionProviders\IncorrectValetDbCredentialsSolutionProvider;
use Spatie\LaravelFlare\Solutions\SolutionProviders\InvalidRouteActionSolutionProvider;
use Spatie\LaravelFlare\Solutions\SolutionProviders\MissingAppKeySolutionProvider;
use Spatie\LaravelFlare\Solutions\SolutionProviders\MissingColumnSolutionProvider;
use Spatie\LaravelFlare\Solutions\SolutionProviders\MissingImportSolutionProvider;
use Spatie\LaravelFlare\Solutions\SolutionProviders\MissingLivewireComponentSolutionProvider;
use Spatie\LaravelFlare\Solutions\SolutionProviders\MissingMixManifestSolutionProvider;
use Spatie\LaravelFlare\Solutions\SolutionProviders\MissingViteManifestSolutionProvider;
use Spatie\LaravelFlare\Solutions\SolutionProviders\OpenAiSolutionProvider;
use Spatie\LaravelFlare\Solutions\SolutionProviders\RunningLaravelDuskInProductionProvider;
use Spatie\LaravelFlare\Solutions\SolutionProviders\SailNetworkSolutionProvider;
use Spatie\LaravelFlare\Solutions\SolutionProviders\TableNotFoundSolutionProvider;
use Spatie\LaravelFlare\Solutions\SolutionProviders\UndefinedViewVariableSolutionProvider;
use Spatie\LaravelFlare\Solutions\SolutionProviders\UnknownMariadbCollationSolutionProvider;
use Spatie\LaravelFlare\Solutions\SolutionProviders\UnknownMysql8CollationSolutionProvider;
use Spatie\LaravelFlare\Solutions\SolutionProviders\UnknownValidationSolutionProvider;
use Spatie\LaravelFlare\Solutions\SolutionProviders\ViewNotFoundSolutionProvider;

return [
    /*
    |
    |--------------------------------------------------------------------------
    | Flare API key
    |--------------------------------------------------------------------------
    |
    | Specify Flare's API key below to enable error reporting to the service.
    |
    | More info: https://flareapp.io/docs/general/projects
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

    'flare_middleware' => [
        RemoveRequestIp::class,
        AddGitInformation::class,
        AddNotifierName::class,
        AddEnvironmentInformation::class,
        AddExceptionInformation::class,
        AddDumps::class,
        AddLogs::class => [
            'maximum_number_of_collected_logs' => 200,
        ],
        AddQueries::class => [
            'maximum_number_of_collected_queries' => 200,
            'report_query_bindings' => true,
        ],
        AddJobs::class => [
            'max_chained_job_reporting_depth' => 5,
        ],
        AddContext::class,
        AddExceptionHandledStatus::class,
        CensorRequestBodyFields::class => [
            'censor_fields' => [
                'password',
                'password_confirmation',
            ],
        ],
        CensorRequestHeaders::class => [
            'headers' => [
                'API-KEY',
                'Authorization',
                'Cookie',
                'Set-Cookie',
                'X-CSRF-TOKEN',
                'X-XSRF-TOKEN',
            ]
        ],
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
    | Ignored Solution Providers
    |--------------------------------------------------------------------------
    |
    | You may specify a list of solution providers (as fully qualified class
    | names) that shouldn't be loaded. Ignition will ignore these classes
    | and possible solutions provided by them will never be displayed.
    |
    */

    'ignored_solution_providers' => [

    ],

    /*
    |--------------------------------------------------------------------------
    | Recorders
    |--------------------------------------------------------------------------
    |
    | Ignition registers a couple of recorders when it is enabled. Below you may
    | specify a recorders will be used to record specific events.
    |
    */

    'recorders' => [
        DumpRecorder::class,
        JobRecorder::class,
        LogRecorder::class,
        QueryRecorder::class,
    ],

    /*
     * When a key is set, we'll send your exceptions to Open AI to generate a solution
     */

    'open_ai_key' => env('IGNITION_OPEN_AI_KEY'),

    /*
   |--------------------------------------------------------------------------
   | Include arguments
   |--------------------------------------------------------------------------
   |
   | Ignition show you stack traces of exceptions with the arguments that were
   | passed to each method. This feature can be disabled here.
   |
   */

    'with_stack_frame_arguments' => true,

    /*
   |--------------------------------------------------------------------------
   | Argument reducers
   |--------------------------------------------------------------------------
   |
   | Ignition show you stack traces of exceptions with the arguments that were
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
];
