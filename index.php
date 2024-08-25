<?php



               function getCommentsStatsForResource(ResourceMetadata $resourceMetadata, Collection $regions): array { // Convert the
                $regionAgsCodes = $regions->pluck('ags_code')->toArray();
                $distributionAreaAgsCodes = $distributionArea->pluck('ags_code')->toArray();
        
                // SQL to get all comments and group by region
                $commentsSql = sprintf(
                    'SELECT r.ags_code, r.title, r.type, COUNT(c.id) AS comments_count
                     FROM regions r
                     LEFT JOIN zip_codes z ON r.ags_code = z.region_ags_code
                     LEFT JOIN users u ON z.zip_code = u.zip
                     LEFT JOIN comments c ON u.id = c.user_id AND c.resource_id = ?
                     WHERE r.ags_code IN (%s)
                     GROUP BY r.ags_code, r.title, r.type',
                    implode(',', array_fill(0, count($regionAgsCodes), '?'))
                );
        
                // Merge resource_id with the array of region AGS codes for binding
                $bindings = array_merge([$resourceMetadata->resource_id], $regionAgsCodes);
        
                // Execute the query to get all comments per region
                $commentsResults = DB::select($commentsSql, $bindings);
        
                // Initialize total comments count
                $totalComments = 0;
        
                // Initialize the comments per region array with all regions and default count of 0
                $commentsPerRegion = $regions->mapWithKeys(function ($region) {
                    return [$region->ags_code => [
                        'title' => $region->title,
                        'type' => $region->type,
                        'comments_count' => 0
                    ]];
                })->toArray();
        
                // Update the comments count for regions with actual data and calculate total comments
                foreach ($commentsResults as $result) {
                    $commentsPerRegion[$result->ags_code]['comments_count'] = $result->comments_count;
                    $totalComments += $result->comments_count;
                }
        
                // Calculate the comment counts within and outside the distribution area
                $commentsWithinDistributionArea = 0;
                $commentsOutsideDistributionArea = 0;
        
                foreach ($commentsPerRegion as $ags_code => $data) {
                    if (in_array($ags_code, $distributionAreaAgsCodes)) {
                        $commentsWithinDistributionArea += $data['comments_count'];
                    } else {
                        $commentsOutsideDistributionArea += $data['comments_count'];
                    }
                }
        
                // Return the total comments, comments count per region, and distribution area stats
                return [
                    'total_comments' => $totalComments,
                    'comments_per_region' => $commentsPerRegion,
                    'comments_within_distribution_area' => $commentsWithinDistributionArea,
                    'comments_outside_distribution_area' => $commentsOutsideDistributionArea,
                ];
            }