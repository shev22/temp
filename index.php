<?php



               function getCommentsStatsForResource(ResourceMetadata $resourceMetadata, Collection $regions): array { // Convert the
             
               // Convert collections to arrays of AGS codes
        $regionAgsCodes = $regions->pluck('ags_code')->toArray();
        $distributionAreaAgsCodes = $distributionArea->pluck('ags_code')->toArray();

        // SQL to get the total comments for the resource
        $totalCommentsSql = 'SELECT COUNT(c.id) AS total_comments
                             FROM comments c
                             WHERE c.resource_id = ?';

        // Execute the query to get the total comments
        $totalCommentsResult = DB::select($totalCommentsSql, [$resourceMetadata->resource_id]);
        $totalComments = $totalCommentsResult[0]->total_comments ?? 0;

        // SQL to get comments count per region
        $commentsPerRegionSql = sprintf(
            'SELECT r.ags_code, COUNT(c.id) AS comments_count
             FROM regions r
             LEFT JOIN zip_codes z ON r.ags_code = z.region_ags_code
             LEFT JOIN users u ON z.zip_code = u.zip
             LEFT JOIN comments c ON u.id = c.user_id AND c.resource_id = ?
             WHERE r.ags_code IN (%s)
             GROUP BY r.ags_code',
            implode(',', array_fill(0, count($regionAgsCodes), '?'))
        );

        // Merge resource_id with the array of region AGS codes for binding
        $bindings = array_merge([$resourceMetadata->resource_id], $regionAgsCodes);

        // Execute the query to get comments per region
        $commentsPerRegionResults = DB::select($commentsPerRegionSql, $bindings);

        // Initialize the comments per region array with all regions and default count of 0
        $commentsPerRegion = $regions->pluck('ags_code')->mapWithKeys(function ($ags_code) {
            return [$ags_code => 0];
        })->toArray();

        // Update the comments count for regions with actual data
        foreach ($commentsPerRegionResults as $result) {
            $commentsPerRegion[$result->ags_code] = $result->comments_count;
        }

        // Calculate the comment counts within and outside the distribution area
        $commentsWithinDistributionArea = 0;
        $commentsOutsideDistributionArea = 0;

        foreach ($commentsPerRegion as $ags_code => $count) {
            if (in_array($ags_code, $distributionAreaAgsCodes)) {
                $commentsWithinDistributionArea += $count;
            } else {
                $commentsOutsideDistributionArea += $count;
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