# 📬 listmonk-backup

A simple PHP library to connect to and interact with the Listmonk API. Ideal for building backups, exports, or integrating Listmonk with external systems.

---

## 🧩 Features

- Connect to a Listmonk instance via API  
- Fetch subscribers  
- Create new subscribers  
- Ready to extend (segments, campaigns, lists, etc.)

---

## 🚀 Getting Started

### 1. Installation

Clone the repository or download `ListmonkAPI.php` into your project:

```bash
git clone https://github.com/your-user/listmonk-backup.git
```

Or include the file manually:

```php
require_once 'ListmonkAPI.php';
```

---

## ⚙️ Configuration using `config.ini`

Instead of environment variables, this project uses a simple `config.ini` file placed **outside the public web root**, e.g.:

### Example `config.ini`:

```ini
LISTMONK_URL=https://your-listmonk-url.com
LISTMONK_USER=admin
LISTMONK_PASS=admin
```

## 🧪 Examples

### Fetch all subscribers

```php
$response = $listmonk->getSubscribers();
print_r($response);
```

### Create a new subscriber

```php
$new = $listmonk->createSubscriber([
    'name'   => 'John Doe',
    'email'  => 'john@example.com',
    'status' => 'enabled',
    'lists'  => [1], // list ID(s)
]);
```

---

## 📄 License

MIT License — use and modify freely.

---

## ✍️ Contributions

Pull requests and ideas are welcome!  
Feel free to open issues for bugs or feature suggestions.
