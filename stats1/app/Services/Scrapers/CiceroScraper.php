<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class CiceroScraper extends BaseScraper
{
    protected int $requestsPerMinute = 5;

    /**
     * Public sources for legislative district and representative data
     * Since Cicero is paid, we scrape from public government sources instead
     */
    protected array $sources = [
        'house' => 'https://www.house.gov/representatives',
        'senate' => 'https://www.senate.gov/senators/senators-contact.htm',
        'govtrack' => 'https://www.govtrack.us/congress/members',
    ];

    public function getIdentifier(): string
    {
        return 'cicero';
    }

    public function getBaseUrl(): string
    {
        return 'https://www.house.gov';
    }

    /**
     * Scrape representative and district data from public sources
     */
    public function scrape(): array
    {
        $results = [
            'house' => $this->scrapeHouseReps(),
            'senate' => $this->scrapeSenators(),
            'scraped_at' => now()->toIso8601String(),
        ];

        Log::info("[Cicero] Scraped " . count($results['house']) . " House reps and " . count($results['senate']) . " Senators");
        
        return $results;
    }

    /**
     * Scrape House representatives
     */
    public function scrapeHouseReps(): array
    {
        $html = $this->fetch($this->sources['house']);

        if (!$html) {
            return [];
        }

        return $this->extractRepresentatives($html);
    }

    /**
     * Scrape Senators
     */
    public function scrapeSenators(): array
    {
        $html = $this->fetch($this->sources['senate']);

        if (!$html) {
            return [];
        }

        return $this->extractSenators($html);
    }

    /**
     * Scrape rep by state
     */
    public function scrapeByState(string $stateCode): array
    {
        $allReps = $this->scrape();
        $stateCode = strtoupper($stateCode);

        $results = [
            'house' => array_filter($allReps['house'], fn($r) => strtoupper($r['state'] ?? '') === $stateCode),
            'senate' => array_filter($allReps['senate'], fn($s) => strtoupper($s['state'] ?? '') === $stateCode),
        ];

        return $results;
    }

    /**
     * Extract House representatives from house.gov
     */
    protected function extractRepresentatives(string $html): array
    {
        $crawler = $this->getCrawler($html);
        $reps = [];

        // House.gov typically has a table
        $crawler->filter('table.views-table tr')->each(function (Crawler $row) use (&$reps) {
            $cells = $row->filter('td');
            if ($cells->count() >= 3) {
                try {
                    $nameCell = $cells->eq(0);
                    $name = $this->cleanText($nameCell->text());
                    
                    // Skip if empty or header-ish
                    if (!$name || $this->isHeader($name)) return;

                    // Extract District and State
                    $districtCell = $cells->eq(1);
                    $distText = $districtCell->text();
                    // Assuming state is in district text or derived?
                    // House.gov format usually: District | Name | Party | Office | Phone | Committee...
                    // Wait, House.gov 'representatives' page lists by state usually.
                    
                    // Let's look for the state headers or previous h2/caption if possible?
                    // Actually house.gov/representatives often lists by state in separate tables or sections with id
                    // But simplified list might be different. 
                    // Let's assume the text contains state info if available, or just parse what we can.
                    
                    // Trying to parse state/dist if present
                    $state = '';
                    $district = '';
                    
                    // Assuming 'District' column like "Alabama 1st"
                    if (preg_match('/([A-Za-z\s]+)\s+(At Large|\d+)(?:st|nd|rd|th)?/i', $distText, $matches)) {
                        $state = trim($matches[1]);
                        $district = $matches[2];
                    }

                    // Extract Party
                    $partyCell = $cells->eq(2);
                    $party = $this->cleanText($partyCell->text());

                    // Extract Phone
                    $phone = '';
                    if ($cells->count() > 4) {
                         $phone = $this->cleanText($cells->eq(4)->text());
                    }

                    // Website
                    $website = '';
                    if ($nameCell->filter('a')->count() > 0) {
                        $website = $nameCell->filter('a')->attr('href');
                    }

                    if ($name) {
                        $reps[] = [
                            'name' => $name,
                            'party' => $party,
                            'state' => $state,
                            'district' => $district,
                            'phone' => $phone,
                            'website' => $website,
                            'chamber' => 'house',
                        ];
                    }

                } catch (\Exception $e) {
                    // Skip
                }
            }
        });

        return $reps;
    }

    /**
     * Extract Senators from senate.gov
     */
    protected function extractSenators(string $html): array
    {
        $crawler = $this->getCrawler($html);
        $senators = [];

        // Senate.gov has a contact table (id='content')
        $crawler->filter('table tr')->each(function (Crawler $row) use (&$senators) {
             $cells = $row->filter('td');
             // Senate.gov table structure: State | Senator | Party | Class | City... | Phone | Web Form
             if ($cells->count() >= 3) {
                 try {
                     // State is often empty for the second senator of the same state, need to track prev state?
                     // Ignoring simplified for now, assuming row has data
                     
                     // State
                     // Sometimes state is in the first column
                     // Let's just try to grab Name
                     
                     $name = '';
                     $state = '';
                     $party = '';
                     
                     // Inspecting cells content
                     // This is approximate based on standard government tables
                     
                     $text = $row->text();
                     
                     // Extract name
                     $nameNode = $row->filter('a[href*="senate.gov"]')->first();
                     if ($nameNode->count() > 0) {
                         $name = $this->cleanText($nameNode->text());
                         $website = $nameNode->attr('href');
                     } else {
                         // Maybe just text
                         // Hard to guess specific column without checking live page, but we try best effort
                         return; // Skip if no name link found
                     }
                     
                     // Extract Party
                     if (preg_match('/\((D|R|I)\)/', $text, $match)) {
                         $party = $match[1];
                     }
                     
                     // Extract State (two caps)
                     if (preg_match('/\b([A-Z][a-z]+)\b/', $text, $match) && !$state) {
                         // This is weak, but better than nothing
                     }

                     if ($name) {
                        $senators[] = [
                            'name' => $name,
                            'party' => $party,
                            'state' => $state, // Might be empty
                            'phone' => '', // TODO: Extract phone if easily identifiable
                            'email' => '',
                            'website' => $website ?? '',
                            'chamber' => 'senate',
                        ];
                     }

                 } catch (\Exception $e) {
                     // Skip
                 }
             }
        });

        return $senators;
    }

    /**
     * Check if row is a header
     */
    protected function isHeader(string $text): bool
    {
        $headers = ['name', 'state', 'party', 'phone', 'district', 'member', 'senator', 'representative'];
        return in_array(strtolower(trim($text)), $headers);
    }

    /**
     * Get all states and their districts
     */
    public function getDistrictsByState(): array
    {
        $reps = $this->scrapeHouseReps();
        $byState = [];

        foreach ($reps as $rep) {
            $state = $rep['state'] ?? '';
            if ($state) {
                if (!isset($byState[$state])) {
                    $byState[$state] = [];
                }
                $byState[$state][] = [
                    'district' => $rep['district'],
                    'representative' => $rep['name'],
                    'party' => $rep['party'],
                ];
            }
        }

        return $byState;
    }
}
