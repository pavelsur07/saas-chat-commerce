site/src/Account/
├── Controller/
│   ├── RegistrationController.php  # Вход в систему
│   ├── SettingsController.php      # Настройки компании
│   └── BillingController.php       # Управление подпиской
├── Service/
│   ├── AccountManager.php          # Оркестратор (создание аккаунта)
│   ├── SubscriptionService.php     # Логика тарифов
│   └── AccessPolicy.php            # Проверка прав внутри компании
├── Entity/
│   ├── Company.php                 # Root Aggregate
│   ├── User.php                    # Member of Company
│   └── Subscription.php            # Billing data
├── Repository/
│   ├── CompanyRepository.php
│   └── UserRepository.php
├── Builder/                        # Для тестов (EntityBuilder)
│   ├── CompanyBuilder.php
│   └── UserBuilder.php
├── DTO/
│   └── RegistrationRequest.php     # Валидация входных данных
└── Form/
└── RegistrationType.php
