<?php



               function getCommentsStatsForResource(ResourceMetadata $resourceMetadata, Collection $regions): array { // Convert the
             
             
                    $regionAgsCodes=$regions->pluck('ags_code')->toArray();

                // Prepare the SQL query to get the total comments for the resource
                $totalCommentsSql = 'SELECT COUNT(c.id) AS total_comments
                FROM comments c
                WHERE c.resource_id = ?';

                // Execute the query to get the total comments
                $totalCommentsResult = DB::select($totalCommentsSql, [$resourceMetadata->resource_id]);
                $totalComments = $totalCommentsResult[0]->total_comments ?? 0;

                // Prepare the SQL query to get the comments count per region
                $commentsPerRegionSql = sprintf(
                'SELECT r.ags_code, COUNT(c.id) AS comments_count
                FROM comments c
                JOIN users u ON c.user_id = u.id
                JOIN zip_codes z ON u.zip = z.zip_code
                JOIN regions r ON z.region_ags_code = r.ags_code
                WHERE c.resource_id = ?
                AND r.ags_code IN (%s)
                GROUP BY r.ags_code',
                implode(',', array_fill(0, count($regionAgsCodes), '?'))
                );

                // Merge resource_id with the array of region AGS codes for binding
                $bindings = array_merge([$resourceMetadata->resource_id], $regionAgsCodes);

                // Execute the query to get the comments per region
                $commentsPerRegionResults = DB::select($commentsPerRegionSql, $bindings);

                // Map the results to region AGS codes with comment counts
                $commentsPerRegion = collect($commentsPerRegionResults)->mapWithKeys(function ($item) {
                return [$item->ags_code => $item->comments_count];
                })->toArray();

                // Return the total comments and the comments count per region
                return [
                'total_comments' => $totalComments,
                'comments_per_region' => $commentsPerRegion,
                ];
                }