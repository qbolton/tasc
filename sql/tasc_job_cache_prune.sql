DELETE FROM tasc_job_cache WHERE cache_date < DATE_SUB(NOW(), INTERVAL 2 DAY );
