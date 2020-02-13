## Содержание
1) [Установка](#инструкции-по-установке-и-использованию-пакета)
2) [Тесты](#тесты)
3) [Инъекция зависимостей в ресурс на базе которого строится коллекция ресурсов](#инъекция-зависимостей-в-ресурс-на-базе-которого-строится-коллекция-ресурсов)
4) [Анонимные расширенные коллекции ресурсов](#анонимные-расширенные-коллекции-ресурсов)
5) [Политики ресурсов](#политики-ресурсов)
6) [Преобразователи ресурсов](#преобразователи-ресурсов)
7) [Хелперы для инспекции некоторых ошибок ресурса](#хелперы-для-инспекции-некоторых-ошибок-ресурса)
8) [Пайплайны](#пайплайны)
9) [Безопасность](#безопасность)

## Инструкции по установке и использованию пакета
Чтобы установить пакет, запустите в терминале следующую команду:
``` bash
composer require moofik/laravel-resources-extensions
```
### Тесты
Для запуска тестов, находясь в корне пакета выполните в терминале комманду
```shell script
composer test
```

### Возможности
#### Расширенные коллекции ресурсов
##### Инъекция зависимостей в ресурс на базе которого строится коллекция ресурсов
При использовании стандартного ResourceCollection в Laravel существуют некоторые ограничения. Одним таких ограничений является отсутствие возможности передавать произвольные аргументы в ресурс на базе которого строится наша коллекция ресурсов (это актуально, до тех пор, пока мы используем в коллекциях ресурсов метод ```parent::toArray()``` или пытаемся создать инстанс ResourceCollection, передав внутрь его конструктора какую-то коллекцию или с помощью вызова ResourceCollection::). По умолчанию, если ресурс на базе которого строится ваша коллекция принимает какие-то произвольные аргументы в конструктор - Laravel пробросит исключение.

Предположим, что у нас есть API Resource, API Resource Collection которая предполагает его использование, и контроллер который использует эту коллекцию. Рассмотрим на их примере использование расширенных коллекций ресурсов.
<br><br>Дадим возможность конструтору __construct у ресурса RepairRequestResource, помимо модели, принимать второй аргумент типа RepairOffersRepository:
``` php
class RepairRequestResource extends JsonResource
{
    private $repairOfferRepository;

    public function __construct(
        RepairRequest $resource,
        RepairOffersRepository $repairOfferRepository
    ) {
        parent::__construct($resource);
        $this->repairOfferRepository = $repairOfferRepository;
    }
    
    public function toArray($request)
    {
        $result = parent::toArray($request);
        $result['offers_count'] = $this->repairOfferRepository->countOffersByRequest($this->resource);
        
        return $result;
    }
}
```

<p>
Пусть, мы хотим использовать коллекцию на базе этого ресурса в контроллере (наша конкретная реализация класса коллекции будет полагаться на вызов ```parent::toArray()```, а следовательно, каждый объект нашей коллекции будет преобразован в RepairRequestResource). По умолчанию, наследуя класс своей коллекции от дефолтного ResourceCollection, наша коллекция не будет знать какой аргумент передавать в конструктор RepairRequestResource.
Эту проблему можно решить отнаследовав метод коллекции от класса: 
```php
Moofik\LaravelResourceExtenstion\Extension\ExtendableResourceCollection
```
class;
</p>

```php
use Moofik\LaravelResourceExtenstion\Extension\ExtendableResourceCollection;

class RepairRequestResourceCollection extends ExtendableResourceCollection
{
    public function toArray($request)
    {
        $result = parent::toArray($request);

        /*
         * Предположительно, здесь мы делаем какие-то действия с нашим $result
         */ 
        
        return $result;
    }
}
```

<p>
Теперь конструктор коллекции RepairRequestResourceCollection будет автоматически передавать нужное число аргументов (идущих после первого обязательного аргумента $resource) в конструктор ресурса RepairRequestResource.
</p>

```php
class RepairRequestController
{
    public function repairRequests(RepairOffersRepository $repairOfferRepository)
    {
        $repairRequests = RepairRequest::all();
        
        return new RepairRequestResourceCollection($repairRequests, $repairOfferRepository);
    }
}
```

<b>Примечание</b>: Если бы мы передали в конструктор RepairRequestResourceCollection дополнительные аргументы, кроме тех, которые нужны для конструктора ресурса RepairRequestResource, эти аргументы были бы доступны внутри нашей коллекции. Мы могли бы обратиться к массиву этих аргументов следующим образом:
```php
$this->args;
```
##### Анонимные расширенные коллекции ресурсов
Чтобы не создавать отдельный класс коллекции ресурсов (например, если нам не нужна кастомная логика в методе ```toArray()```, или всю кастомную логику было решено вынести в политики и преобразователи ресурсов (эти темы будут рассмотрены разделы 2 и 3 документации)) можно воспользоваться статическим методом "extendableCollection". Он возвращает экземпляр класса ```Moofik\LaravelResourceExtenstion\Extension\ExtendableResourceCollection```, дополнительно мы можем передать в него класс используемый для создания коллекции ресурса и набор аргументов для конструктора этого ресурса. По умолчанию, анонимная коллекция использует класс ```Moofik\LaravelResourceExtenstion\Extension\RestrictableResource```, на основе которого создается каждый отдельный ресурс в коллекции.

#### Политики ресурсов
<p>Если вам требуется каким-либо образом заставить ваш ресурс принудительно скрывать или делать видимыми поля используемых в ресурсе моделей, особенно в случае если эта логика является достаточно сложной и опирается на какие-то внешние зависимости - вы можете использовать политики ресурсов. Например, пусть нам необходимо отображать в конечном результате, в который сериалиуется ресурс, некоторые поля модели, которые зависят от роли пользователя (это условие приведено лишь для примера, логика отображения или скрытия полей может быть абсолютно любой):
</p>
<p>Первое, что нам нужно - отнаследовать нашу будущую политику от класса ResourcePolicy. Назовем этот класс RepairRequestPolicy. Внутри него будет располагаться логика отображения/скрытия полей модели используемой ресурсом. </p>

```php
use App\User;
use App\RepairRequest;
use Moofik\LaravelResourceExtenstion\Policy\ResourcePolicy;

class RepairRequestPolicy extends ResourcePolicy
{
    /**
     * @var User
     */
    private $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * @param RepairRequest $repairRequest
     * @return array
     */
    public function getHiddenFields($repairRequest): array
    {
        if (!$this->user->hasRole(User::USER_ROLE_WORKSHOP)) {
            return ['description', 'city', 'details'];
        }

        return [];
    }

    /**
     * @param RepairRequest $repairRequest
     * @return array
     */
    public function getVisibleFields($repairRequest): array
    {
        return [];
    }
}
```
Теперь, если мы применим эту политику к нашему ресурсу, то поля description, city and details будут скрыты если у пользователя нет роли "Workshop". Чтобы применить политику к ресурсу, нужно чтобы ресурс наследовал следующий класс
```php
Moofik\LaravelResourceExtenstion\Extension\RestrictableResource;
```
Создадим следующий API Resource:

```php
use App\RepairRequest;
use Moofik\LaravelResourceExtenstion\Extension\RestrictableResource;

class RepairRequestResource extends RestrictableResource
{
    /**
     * RepairRequest constructor.
     * @param  RepairRequest  $resource
     */
    public function __construct(RepairRequest $resource)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        /* Если вы хотите чтобы политики работали корректно, вы должны либо полагаться на вызов
         * parent::toArray() либо вызывать метод $this->resolvePolicies() напрямую, в начале метода toArray вашего ресурса
         */  
        return parent::toArray($request);
    }
}
```

После этого у ресурса появится несколько новых методов. Первый (как раз тот, что нам нужен сейчас) - метод "applyPolicy", который применяет политику к ресурсу, и возвращает сам ресурс (и поэтому мы можем использовать чейнинг - цепочки методов - или возвращать результат метода "applyPolicy" прямо из методов контроллера)
Политики ресурсов так же могут быть использованы с ExtendableResourceCollection и его наследниками (смотри пункт 1. документации). В этом случае политики будут применены к каждому подклассу RestrictableResource, на который полагается наша коллекция. Ниже приведен пример использования политик в методах контроллера вместе с обычными ресурсами и коллекциями ресурсов:
```php
class RepairRequestController
{
     public function allRequests(Guard $guard, RepairOffersRepository $repairOfferRepository)
     {
        /** @var User $user */
        $user = $guard->user();
        $repairRequests = RepairRequest::paginate();
    
        $resource = new RepairRequestResourceCollection($repairRequests, $repairOfferRepository);
        $resourcePolicy = new RepairRequestPolicy($user);
    
        return $resource->applyPolicy($resourcePolicy);
     }
     
     public function oneRequest(int $id, Guard $guard, RepairOffersRepository $repairOfferRepository)
     {
        /** @var User $user */
        $user = $guard->user();
        $repairRequest = RepairRequest::find($id);
    
        $resource = new RepairRequestResource($repairRequest, $repairOfferRepository);
        $resourcePolicy = new RepairRequestPolicy($user);
    
        return $resource->applyPolicy($resourcePolicy);
     }
}
```

Примечания:
 1) Вы можете применять множество политик к одному ресурсу единовременно, но необходимо делать это осторожно. Порядок применяемых политик имеет значение. Политики будут применены последовательно, в таком же порядке, в котором вы применили их на ресурсе.
 2) <b>Внутри метода toArray() вашего ресурса вы ДОЛЖНЫ либо вызывать ```parent::toArray($request)``` либо же, если вы не хотите полагаться на использование родительского toArray(), напрямую вызывать метод ```$this->resolvePolicies()```  для того чтобы политики работали должным образом.</b>
#### Преобразователи ресурсов
<p>Если вам нужна какая-то пост-обработка массива который формируется внутри метода ```toArray()``` вашего ресурса или коллекции ресурсов вы можете использовать преобразователи ресурсов. Представим, что нам нужно прикрепить флаг "is_offer_made" к результату метода ```toArray()``` нашего ресурса. Логика прикрепления флага будет основана на простой идее: "принадлежит ли данному пользователю оффер". Если оффер принадлежит данному пользователю, будем устанавливать данный флаг в true, в противном случае значение флага должно быть false.
</p>
<p>Первое что нам нужно - создать преобразователь ресурса, отнаследовав его от калсса ```Moofik\LaravelResourceExtenstion\Transformer\ResourceTransformer```. Внутри него будет описана описанных выше преобразований.</p>

```php
use App\User;
use App\RepairRequest;
use Moofik\LaravelResourceExtenstion\Transformer\ResourceTransformer;

class RepairRequestWorkshopTransformer extends ResourceTransformer
{
    /**
     * @var RepairRequestOffersRepository
     */
    private $repairRequestOffersRepository;

    /**
     * @var User
     */
    private $user;

    public function __construct(User $user, RepairRequestOffersRepository $repairRequestOffersRepository)
    {
        $this->user = $user;
        $this->repairRequestOffersRepository = $repairRequestOffersRepository;
    }

    /**
     * @param  RepairRequest  $resource
     * @param  array  $data
     * @return array
     */
    public function transform($resource, array $data): array
    {
        if (!$resource instanceof RepairRequest) {
            throw new InvalidArgumentException(sprintf('Invalid argument passed into %s::transform()', __CLASS__));
        }

        $offer = $this->repairRequestOffersRepository->findOneByRepairRequestAndWorkshop($resource, $this->user);
        $data['is_offer_make'] = (null === $offer) ? false : true;

        return $data;
    }
}
```
Чтобы мы могли применить преобразователь на ресурсе, ресурс должен наследовать класс ```Moofik\LaravelResourceExtenstion\Extension\RestrictableResource``` или быть экземляром класса ```Moofik\LaravelResourceExtenstion\Extension\RestrictableResource```. После того, как мы используем преобразователь на ресурсе, ресурс запустит его автоматически, по окончании преобразования данных при вызове ```parent::toArray()```.
В случае полностью кастомной логики метода '''toArray()''' вашего ресурса вы должны вручную вызвать метод ```$this->resolveTransformation($data)``` для запуска преобразователей ресурсов в требуемом месте. 

<br>
На этот раз, для того, чтобы применить коллекцию на ресурсе воспользуемся другим методом унаследованным от класса ```Moofik\LaravelResourceExtenstion\Extension\RestrictableResource```. Нужный нам метод называется "applyTransformer". В качестве результата выполнения этот метод возвращает инстанс текущего ресурса или коллекции ресурсов, благодаря этому мы можем использовать цепчки методов (например применить несколько преобразователей подряд) или вернуть результат выполнения из метода контроллера. Он будет преобразован в корректный Response. Рассмотрим на примере:

```php
use Moofik\LaravelResourceExtenstion\Extension\RestrictableResource;

class RepairRequest extends RestrictableResource
{
    /**
     * RepairRequest constructor.
     * @param  RepairRequest  $resource
     */
    public function __construct(RepairRequest $resource)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return parent::toArray($request);
    }
}
```
Кстати, мы также можем использовать преобразователи ресурсов вместе с классом ExtendableResourceCollection или его потомками! В этом случае каждый заданный преобразователь ресурса будет последовательно применен к каждому отдельному ресурсу коллекции.
 Повторю, что это будет актуально, только в нескольких случаях: в случае создания коллекции с помощью метода ExtendableResourceCollection::extendableCollection($collection) с дефолтными параметрами -  
 в этом случае, для представления ресурсов внутри коллекции используется класс RestrictableResource, который "из коробки" позволяет применять к нему политики, преобразователи и цепочки - следовательно коллекция которая была создана этим методом также будет корректно работать с политиками, преобразователями и цепочками;
  в случае вызова ```parent::toArray()``` внутри метода ```toArray()``` <b>РЕСУРСА</b> (да, речь идет именно об одиночном ресурсе, а не о коллекции ресурсов) к которому мы хотим применять преобразователи;
 в случае ручного вызова метода ```$this->resolveTransformation($data)``` в том месте метода toArray вашего ресурса, где вы хотите применить преобразователи ресурса).
 Ниже приведен пример использования преобразователей ресурса в методах контроллера:
```php
class RepairRequestController
{
    public function all(Guard $guard, RepairOffersRepository $repairOfferRepository)
    {
        /** @var User $user */
        $user = $guard->user();
        $repairRequests = RepairRequest::paginate();
        
        $resource = new RepairRequestResourceCollection($repairRequests, $repairOfferRepository);
        $resourceTransformer = new RepairRequestTransformer($user);
        
        return $resource->applyTransformer($resourceTransformer);
    }
    
    public function one(int $id, Guard $guard, RepairOffersRepository $repairOfferRepository)
    {
        /** @var User $user */
        $user = $guard->user();
        $repairRequest = RepairRequest::find($id);
        
        $resource = new RepairRequestResource($repairRequest, $repairOfferRepository);
        $resourceTransformer = new RepairRequestTransformer($user);
        
        return $resource->applyTransformer($resourceTransformer);
    }
}
```
Примечания:
 1) Вы можете применить несколько преобразователей ресурса к одному и тому же ресурсы, но будьте внимательны, поскольку порядок их применения имеет значение. Порядок запуска преобразователей ресурса на каждом отдельном ресурсе коллекции будет таким же, как порядок применения преобразователей ресурса к коллекции ресурса.
 2) <b>Вы должны либо полагаться на вызов ```parent::toArray($request)```, либо же вызывать напрямую метод ```$this->resolveTransformation($data)``` внутри метода toArray() вашего ресурса, либо создавать анонимную коллекцию с помощью 
  метода ExtendableResourceCollection::extendableCollection() с дефолтными параметрами, для того чтобы преобразователи ресурсов могли работать.</b> Преобразователи ресурсов будут так же работать если в качестве $underlyingClass метода ExtendableResourceCollection::extendableCollection() будет передано имя класса,
   метод ```toArray()``` которого использует ```parent::toArray($request)```, либо ```$this->resolveTransformation($data)```.
#### Хелперы для инспекции некоторых ошибок ресурса
<p>Этот пакет также имеет несколько полезных трейтов с хелперами, которые можно использовать внутри JsonResource, ResourceCollection и всех их подклассов, включая ExtendableResourceCollection и RestrictableResource.This small package has a couple of error inspection helper methods to use inside your resources which are convenient in some cases. To use them just add next trait to your class

```php
use Moofik\LaravelResourceExtenstions\Extension\ErrorInspectionHelpers;
```
<br>
Методы-хелперы трейта ErrorInspectionHelpers перечислены ниже:
<p>

```php
public function throwIfResourceIsNot(string $expectedClass) // выбросит ошибку, в случае если переданный в API Resource объект используемый для создания ресурса (это может быть модель, пагинатор, коллекция или какой-то другой класс) имеет класс с именем не идентичным параметру $expectedClass

public function throwIfNot($instance, string $expectedClass) //  // выбросит ошибку, в случае если переданный в метод аргумент $instance имеет класс с именем не идентичным параметру $expectedClass

public function throwIfNotAnyOf($instance, array $expectedClasses) // выбросит ошибку, в случае если переданный в метод аргумент $instance имеет класс с именем не совпадающим ни с одним значением из массива $expectedClass
```
<br>

</p>

#### Пайплайны
Цепочка ресурсов и преобразователей (пайплайн) представляет собой конфигурационный класс, который определяет какие ресурсы и преобразователи будут применены к ресурсу, на котором будет использованная данная цепочка.
Пайплайны могут быть полезны, если вы хотите использовать один и тот же набор политик и преобразователей для ресурсов в разных местах программы (самый очевидный пример - в разных методах одного контроллера). 
Каждый пайплайн должен наследовать класс ```use Moofik\LaravelResourceExtenstion\Pipeline\ExtensionPipeline```.
Ниже приведен простой пример пайплайна и его использования:

```php
namespace App\Http\Resources\Pipeline;

use App\User;
use App\Repository\RepairRequestOfferRepository;
use App\Repository\RepairRequestViewerRepository;
use App\Http\Resources\Policy\RepairRequestPolicy;
use App\Http\Resources\Transformer\RepairRequestViewersTransformer;
use App\Http\Resources\Transformer\RepairRequestOffersTransformer;
use Moofik\LaravelResourceExtenstion\Pipeline\ExtensionPipeline;

class RepairRequestExtensionPipeline extends ExtensionPipeline
{
    public function __construct(User $user, RepairRequestOfferRepository $offerRepository, RepairRequestViewerRepository $viewerRepository)
    {
        $this
            ->addPolicy(new RepairRequestPolicy($user, $offerRepository))
            ->addTransformer(new RepairRequestOffersTransformer($user, $offerRepository))
            ->addTransformer(new RepairRequestViewersTransformer($user, $viewerRepository));
    }
}
```

Теперь мы можем использовать этот пайплайн с разными ресурсами в разных местах приложения (с одинаковым успехом вместе с ExtendableResourceCollection, RestrictableResource и их наследниками):

```php
use App\User;
use App\RepairRequest;
use App\Repository\RepairRequestOfferRepository;
use App\Repository\RepairRequestViewerRepository;
use App\Http\Resources\Pipeline\RepairRequestExtensionPipeline;
use App\Http\Resources\RepairRequestResource;
use App\Http\Resources\RepairRequestResourceCollection;
use Illuminate\Contracts\Auth\Guard;

class RepairRequestController
{
    public function repairRequests(
        Guard $guard,
        RepairRequestOfferRepository $offerRepository,
        RepairRequestViewerRepository $viewerRepository
    ) {
        $repairRequests = RepairRequest::all();
        $pipeline = new RepairRequestExtensionPipeline($guard->user(), $offerRepository, $viewerRepository);
        $resource = new RepairRequestResourceCollection($repairRequests, $offerRepository);

        return $resource->applyPipeline($pipeline);
    }

    public function repairRequest(
        int $id, 
        Guard $guard,
        RepairRequestOfferRepository $offerRepository,
        RepairRequestViewerRepository $viewerRepository
    ) {
        $repairRequest = RepairRequest::find($id);
        $pipeline = new RepairRequestExtensionPipeline($guard->user(), $offerRepository, $viewerRepository);
        $resource = new RepairRequestResource($repairRequest, $offerRepository);

        return $resource->applyPipeline($pipeline);
    }
}
```

### Безопасность

Если вы обнаружите какие-то проблемы связанные с безопасностью в коде пакета, пожалуйста напишите об этом на электронную почту [moofik12@gmail.com](mailto:moofik12@gmail.com). Не используйте issue tracker для обсуждения проблем с безопасностью.