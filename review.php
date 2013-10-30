<?php
$usage = "
Usage:
    php review.php --input ravintolat.csv

Parameters
    --input     Name of the .csv file to read
";

$parameters = getopt('', array('input:'));
if (! isset($parameters['input'])) {
    echo $usage;
    exit(1);
}

$andyHauler = new RestaurantCritic(new Secretary());

$reviews = $andyHauler->reviewRestaurantsFromList($parameters['input'])
    ->publishReview();

echo $reviews, "\n";
exit(1);

/**
 * Class RestaurantCritic
 *
 * RestaurantCritic compares restaurants against each others and publishes review
 * Doesn't actually make the review (private Secretary does that)
 * RestaurantCritic likes restaurants that are open longer and gives stars accordingly
 */
class RestaurantCritic
{
    /**
     * RestaurantCritic has private secretary =)
     * @var Secretary
     */
    private $secretary;

    /**
     * @var Restaurant
     */
    private $favoriteRestaurant;

    /**
     * @var Restaurant
     */
    private $leastFavoriteRestaurant;

    /**
     * @param Secretary $secretary
     */
    public function __construct(Secretary $secretary)
    {
        $this->secretary = $secretary;
    }

    /**
     * @param string $fileName
     * @throws InvalidArgumentException
     * @return RestaurantCritic
     */
    public function reviewRestaurantsFromList($fileName)
    {
        if ( ! file_exists($fileName) || ! is_readable($fileName))
        {
            throw new InvalidArgumentException("Non-existing or unreadable file '$fileName'");
        }
        $handle = fopen($fileName, 'r');
        if (!$handle)
        {
            throw new InvalidArgumentException("Unable to read restaurant list from '$fileName'");
        }

        while ($infoRow = fgets($handle))
        {
            $restaurant = $this->secretary->writeRestaurantReview($infoRow);
            $this->reviewRestaurant($restaurant)
                ->compareRestaurants($restaurant);
        }
        return $this;
    }

    /**
     * @return string
     */
    public function publishReview()
    {
        $review = sprintf("My favorite: %s%s, open %d hours a week\n",
            $this->favoriteRestaurant->getName(),
            $this->favoriteRestaurant->getStars(),
            $this->favoriteRestaurant->getTotalOpeningHours()
        );
        $review .= sprintf("My least favorite: %s%s, open %d hours a week\n",
            $this->leastFavoriteRestaurant->getName(),
            $this->leastFavoriteRestaurant->getStars(),
            $this->leastFavoriteRestaurant->getTotalOpeningHours()
        );
        return $review;
    }

    private function compareRestaurants(Restaurant $newRestaurant)
    {
        isset($this->favoriteRestaurant) || $this->favoriteRestaurant = $newRestaurant;
        isset($this->leastFavoriteRestaurant) || $this->leastFavoriteRestaurant = $newRestaurant;

        if ($this->favoriteRestaurant->isOpenLessThan($newRestaurant))
        {
            $this->favoriteRestaurant = $newRestaurant;
        }
        else if ($this->leastFavoriteRestaurant->isOpenLongerThan($newRestaurant))
        {
            $this->leastFavoriteRestaurant = $newRestaurant;
        }
    }
    
    private function reviewRestaurant(Restaurant $restaurant)
    {
        $restaurant->setStars((int)($restaurant->getTotalOpeningHours() / 10));
        return $this;
    }
}

/**
 * Class Secretary
 */
class Secretary
{
    /**
     * @param string $infoRow a row from the .csv file
     * @return Restaurant
     */
    public function writeRestaurantReview($infoRow)
    {
        $info = $this->readInfo($infoRow);
        $review = new Restaurant($info['name']);

        foreach($this->readOpeningHours($info['openingHours']) as $day => $hoursOpen)
        {
            $review->setOpeningHour($day, $hoursOpen);
        }
        return $review;
    }

    private function readInfo($infoRow)
    {
        $columns = ['id', 'name', 'postalCode', 'city', 'openingHours'];
        $values = explode(';', $infoRow);
        $combination = [];
        foreach ($columns as $index => $column)
        {
            $combination[$column] = $values[$index];
        }

        return $combination;
    }

    private function readOpeningHours($openingHoursAsString)
    {
        $parts = $this->splitTimeToParts($openingHoursAsString);
        $openingDays = [];
        foreach ($parts as $weekdayAndTime)
        {
            list($weekday, $openingHours) = $this->splitWeekDaysAndTimes($weekdayAndTime);

            $startDay = self::weekDayToNumber(substr($weekday, 0, 2));
            $endDay = self::weekDayToNumber(substr($weekday, -2));
            $hoursInDay = self::calculateHoursInDay($openingHours);

            while ($startDay <= $endDay)
            {
                $openingDays[$startDay++] = $hoursInDay;
            }
        }
        return $openingDays;
    }

    private function splitTimeToParts($openingHoursAsString)
    {
        return explode(', ', $openingHoursAsString);
    }

    private function splitWeekDaysAndTimes($dayAndTimes)
    {
        return explode(' ', $dayAndTimes, 2);
    }

    private static function weekDayToNumber($day)
    {
        $mapping = [
            'ma' => 0,
            'ti' => 1,
            'ke' => 2,
            'to' => 3,
            'pe' => 4,
            'la' => 5,
            'su' => 6,
        ];
        return $mapping[strtolower($day)];
    }

    private static function calculateHoursInDay($daysOpeningHours)
    {
        $noonAndAfternoon = explode(' ja ', $daysOpeningHours);
        $total = 0;
        foreach ($noonAndAfternoon as $time)
        {
            list($opens, $closes) = explode('-', $time);
            $total += self::timeToHours($closes) - self::timeToHours($opens);
        }
        return $total;
    }

    private static function timeToHours($timeAsString)
    {
        list($hours, $minutes) = explode(':', $timeAsString);
        return (int)$hours + ((int)$minutes/60);
    }
}

/**
 * Class Restaurant
 */
class Restaurant
{
    private $openingHours = [];
    private $name;
    private $stars;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function setOpeningHour($weekday, $hoursOpen)
    {
        $this->openingHours[$weekday] = $hoursOpen;
    }

    public function getTotalOpeningHours()
    {
        $total = 0;
        foreach ($this->openingHours as $hoursOpen)
        {
            $total += $hoursOpen;
        }
        return $total;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setStars($n)
    {
        $this->stars = $n;
    }

    public function getStars()
    {
        return str_repeat('*', $this->stars);
    }

    public function isOpenLongerThan(Restaurant $anotherReview)
    {
        return $this->getTotalOpeningHours() > $anotherReview->getTotalOpeningHours();
    }

    public function isOpenLessThan(Restaurant $anotherReview)
    {
        return $this->getTotalOpeningHours() < $anotherReview->getTotalOpeningHours();
    }
}