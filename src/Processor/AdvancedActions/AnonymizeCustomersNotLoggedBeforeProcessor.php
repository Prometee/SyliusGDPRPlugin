<?php

declare(strict_types=1);

namespace Synolia\SyliusGDPRPlugin\Processor\AdvancedActions;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Synolia\SyliusGDPRPlugin\Processor\AnonymizerProcessor;

class AnonymizeCustomersNotLoggedBeforeProcessor implements AdvancedActionsFormDataProcessorInterface
{
    private EntityManagerInterface $entityManager;

    private AnonymizerProcessor $anonymizerProcessor;

    private ParameterBagInterface $parameterBag;

    private FlashBagInterface $flashBag;

    public function __construct(
        EntityManagerInterface $entityManager,
        AnonymizerProcessor $anonymizerProcessor,
        ParameterBagInterface $parameterBag,
        FlashBagInterface $flashBag
    ) {
        $this->entityManager = $entityManager;
        $this->anonymizerProcessor = $anonymizerProcessor;
        $this->parameterBag = $parameterBag;
        $this->flashBag = $flashBag;
    }

    /** @inheritdoc */
    public function process(string $formTypeClass, FormInterface $form): void
    {
        /** @var string $shopUser */
        $shopUser = $this->parameterBag->get('sylius.model.shop_user.class');
        /** @var array $data */
        $data = $form->getData();

        $shopUsers = $this->entityManager
            ->createQueryBuilder()
            ->select('su')
            ->from($shopUser, 'su')
            ->where('su.lastLogin < :before')
            ->setParameter('before', $data['anonymize_customers_not_logged_before_date'])
            ->getQuery()
            ->execute()
        ;

        if (!is_array($shopUsers)) {
            throw new \LogicException('Error with query.');
        }

        $this->anonymizerProcessor->anonymizeEntities($this->getCustomersFromShopUsers($shopUsers));

        $this->flashBag->add('success', sprintf('%d customers anonymized.', $this->anonymizerProcessor->getAnonymizedEntityCount()));
    }

    private function getCustomersFromShopUsers(array $shopUsers): array
    {
        $customers = [];

        /** @var ShopUserInterface $shopUser */
        foreach ($shopUsers as $shopUser) {
            $customer = $shopUser->getCustomer();
            if (!$customer instanceof CustomerInterface) {
                continue;
            }

            $customers[] = $customer;
        }

        return $customers;
    }

    public function getFormTypesClass(): array
    {
        return ['Synolia\SyliusGDPRPlugin\Form\Type\Actions\AnonymizeCustomersNotLoggedBeforeType'];
    }
}
