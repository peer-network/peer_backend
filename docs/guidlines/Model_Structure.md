## Model Structure

This simple, consistent way to query the database using `Model` methods.

- Core model methods live in `src\App\Models\Core\Model.php`.
- To use them, create your own model class that extends the core `Model` (e.g., `src\App\Models\UserReport.php`).
- Your model must implement the required abstract method `table()` to specify the underlying database table name.

Example outline:

```php
<?php

namespace App\Models;

use App\Models\Core\Model;

class UserReport extends Model
{
    protected function table(): string
    {
        return 'user_reports';
    }
}
```

After extending `Model`, your class can use the shared query helpers exposed by the core model (e.g., find, create, update). Implement `table()` to bind the model to the correct table.