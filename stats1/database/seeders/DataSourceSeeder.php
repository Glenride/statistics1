<?php

namespace Database\Seeders;

use App\Models\DataSource;
use Illuminate\Database\Seeder;

class DataSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Core data sources (already configured)
        $coreSources = [
            [
                'type' => 'fec',
                'name' => 'Federal Election Commission',
                'description' => 'Candidate fundraising, cash-on-hand, and PAC spending data',
                'category' => 'core',
                'is_custom' => false,
                'pipeline_enabled' => true,
                'sync_handler' => 'App\\Services\\FECService',
            ],
            [
                'type' => 'fred',
                'name' => 'Federal Reserve Economic Data',
                'description' => 'CPI (Inflation), Unemployment Rate, GDP, and Consumer Confidence',
                'category' => 'core',
                'is_custom' => false,
                'pipeline_enabled' => true,
                'sync_handler' => 'App\\Services\\FREDService',
            ],
            [
                'type' => 'polling',
                'name' => 'FiveThirtyEight Polling',
                'description' => 'National and state polling averages from FiveThirtyEight',
                'category' => 'core',
                'is_custom' => false,
                'pipeline_enabled' => true,
                'sync_handler' => 'App\\Services\\PollingService',
            ],
        ];

        // Polling sources (scraper-based)
        $pollingSources = [
            [
                'type' => 'rcp',
                'name' => 'RealClearPolitics',
                'description' => 'Scraped polling data from RealClearPolitics (national + battleground)',
                'category' => 'polling',
                'is_custom' => false,
                'pipeline_enabled' => true,
                'sync_handler' => 'App\\Services\\Scrapers\\RCPScraper',
            ],
            [
                'type' => 'press_monitor',
                'name' => 'Press Release Monitor',
                'description' => 'Monitors NYT/Siena, Quinnipiac, Marist, Monmouth for new polls',
                'category' => 'polling',
                'is_custom' => false,
                'pipeline_enabled' => true,
                'sync_handler' => 'App\\Services\\Scrapers\\PressReleaseScraper',
            ],
            [
                'type' => 'pdf_hunter',
                'name' => 'PDF Hunter',
                'description' => 'Finds and parses academic poll PDFs (Suffolk, Emerson)',
                'category' => 'polling',
                'is_custom' => false,
                'pipeline_enabled' => true,
                'sync_handler' => 'App\\Services\\Scrapers\\PDFHunterScraper',
            ],
        ];

        // Free API sources
        $apiSources = [
            [
                'type' => 'google_civic',
                'name' => 'Google Civic Information',
                'description' => 'Representatives, polling places, election info. Get key: console.cloud.google.com',
                'category' => 'core',
                'is_custom' => false,
                'pipeline_enabled' => false, // Disabled until API key configured
                'sync_handler' => 'App\\Services\\GoogleCivicService',
            ],
            [
                'type' => 'opensecrets',
                'name' => 'OpenSecrets',
                'description' => 'Campaign finance, donor data, lobbying. Get key: opensecrets.org/api',
                'category' => 'core',
                'is_custom' => false,
                'pipeline_enabled' => false,
                'sync_handler' => 'App\\Services\\OpenSecretsService',
            ],
            [
                'type' => 'propublica',
                'name' => 'ProPublica Campaign Finance',
                'description' => 'FEC filings, committee data, updated every 15 min. Get key: propublica.org',
                'category' => 'core',
                'is_custom' => false,
                'pipeline_enabled' => false,
                'sync_handler' => 'App\\Services\\ProPublicaService',
            ],
            [
                'type' => 'votesmart',
                'name' => 'Vote Smart',
                'description' => 'Candidate bios, voting records, ratings. Get key: votesmart.org/share/api',
                'category' => 'core',
                'is_custom' => false,
                'pipeline_enabled' => false,
                'sync_handler' => 'App\\Services\\VoteSmartService',
            ],
            [
                'type' => 'usagov',
                'name' => 'USA.gov Social Media',
                'description' => 'Federal officials social media accounts. No API key needed!',
                'category' => 'core',
                'is_custom' => false,
                'pipeline_enabled' => true,
                'sync_handler' => 'App\\Services\\USAGovService',
            ],
        ];

        // Sources with scrapers (formerly enterprise-only)
        $scraperSources = [
            [
                'type' => 'ap_elections',
                'name' => 'Associated Press Elections',
                'description' => 'Race calls, vote counts, delegate tracking. Scraped from AP News.',
                'category' => 'polling',
                'is_custom' => false,
                'pipeline_enabled' => true,
                'sync_handler' => 'App\\Services\\Scrapers\\APElectionsScraper',
            ],
            [
                'type' => 'nationbuilder',
                'name' => 'NationBuilder',
                'description' => 'Campaign events and volunteer data. Scraped from public sites.',
                'category' => 'polling',
                'is_custom' => false,
                'pipeline_enabled' => true,
                'sync_handler' => 'App\\Services\\Scrapers\\NationBuilderScraper',
            ],
            [
                'type' => 'ballotpedia',
                'name' => 'Ballotpedia',
                'description' => 'Candidate info, district maps, election dates. Scraped from ballotpedia.org.',
                'category' => 'core',
                'is_custom' => false,
                'pipeline_enabled' => true,
                'sync_handler' => 'App\\Services\\Scrapers\\BallotpediaScraper',
            ],
            [
                'type' => 'cicero',
                'name' => 'Cicero (Alt)',
                'description' => 'Representative data from house.gov/senate.gov. Free alternative.',
                'category' => 'core',
                'is_custom' => false,
                'pipeline_enabled' => true,
                'sync_handler' => 'App\\Services\\Scrapers\\CiceroScraper',
            ],
        ];

        // Enterprise/Paid sources (stubbed for future - no scraper available)
        $enterpriseSources = [
            [
                'type' => 'ballotready',
                'name' => 'BallotReady',
                'description' => 'Elections, candidates, polling places, measures. Contact sales.',
                'category' => 'enterprise',
                'is_custom' => false,
                'pipeline_enabled' => false,
                'sync_handler' => null,
            ],
            [
                'type' => 'democracy_works',
                'name' => 'Democracy Works Elections',
                'description' => 'State/local election calendars, ballot info. Contact for access.',
                'category' => 'enterprise',
                'is_custom' => false,
                'pipeline_enabled' => false,
                'sync_handler' => null,
            ],
            [
                'type' => 'vip',
                'name' => 'Voting Information Project',
                'description' => 'Official election data from state officials. Contact for access.',
                'category' => 'enterprise',
                'is_custom' => false,
                'pipeline_enabled' => false,
                'sync_handler' => null,
            ],
        ];

        // Seed all sources
        $allSources = array_merge($coreSources, $pollingSources, $apiSources, $scraperSources, $enterpriseSources);

        foreach ($allSources as $source) {
            DataSource::updateOrCreate(
                ['type' => $source['type']],
                $source
            );
        }

        $count = count($allSources);
        $this->command->info("Data sources seeded successfully! ({$count} sources)");
    }
}
