<?php



               function getCommentsStatsForResource(ResourceMetadata $resourceMetadata, Collection $regions): array { // Convert the
                $commentsStatsSql = sprintf(
                    'SELECT 
                        r.ags_code,
                        r.title,
                        r.type,
                        COUNT(c.id) AS comments_count,
                        SUM(CASE WHEN c.id IS NOT NULL THEN 1 ELSE 0 END) OVER() AS total_comments
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
        
                // Execute the combined query to get the stats
                $commentsStatsResults = DB::select($commentsStatsSql, $bindings);
        
                // Initialize the comments per region array with all regions and default count of 0
                $commentsPerRegion = $regions->mapWithKeys(function ($region) {
                    return [$region->ags_code => [
                        'title' => $region->title,
                        'type' => $region->type,
                        'comments_count' => 0
                    ]];
                })->toArray();
        
                // Initialize total comments count
                $totalComments = 0;
        
                // Update the comments count for regions with actual data and set total comments
                foreach ($commentsStatsResults as $result) {
                    $commentsPerRegion[$result->ags_code]['comments_count'] = $result->comments_count;
                    $totalComments = $result->total_comments;
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
            